<?php
class Article{
    public $name;
    public $sourceName;
    public $content;

    public function setProp(string $name,string $sourceName,string $content):void{
        $this->name = $name;
        $this->sourceName = $sourceName;
        $this->content = $content;
    }

    public function __construct(string $name,string $sourceName,string $content)
    {
        $this->name = $name;
        $this->sourceName = $sourceName;
        $this->content = $content;
    }
}
class XMLParser{
    private $xmlFile;
    private $articles = [];

    public function loadRssFile(string $path):SimpleXMLElement{
        $this->xmlFile = simplexml_load_file($path);
        if(!$this->xmlFile) throw new Exception('Erreur lors de l\'accés au flux RSS.');
        return $this->xmlFile;
    }

    public function getArticlesFromXml(SimpleXMLElement $xmlElement,string $sourceName):Array{
        for($i = 0; $i<count($xmlElement->channel->item);$i++){
            $name = $xmlElement->channel->item[$i]->title->__toString();
            $content = $xmlElement->channel->item[$i]->description->__toString();
            $this->articles[$i] = new Article($name,$sourceName,$content);
        }
        return $this->articles;
    }
}
class DatabaseUtils{
    private $db;
    private $articles = [];
    
    public function __construct(string $bdd,string $user, string $pwd,string $bddName){
        try {
            $this->db = new PDO("mysql:host=$bdd;dbname=$bddName;charset=utf8", $user, $pwd);
        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }

    public function getArticlesFromDatabase():Array{
        $query = $this->db->prepare('SELECT a.name,a.content,s.name AS sourceName FROM article AS a INNER JOIN source AS s ON a.source_id = s.id ');
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE,Article::class,['','','']);
        $this->articles = array_merge($this->articles,$results);
        return $this->articles;
    }

}

class ArticleAgregator implements Iterator
{

    private $allArticles=[];
    private $db;
    private $xml;
    private $position = 0;

    public function appendRss(string $sourceName, string $path):void{
        try{
            $this->xml = new XMLParser();
            $xmlElement = $this->xml->loadRssFile($path);
            $articles = $this->xml->getArticlesFromXml($xmlElement,$sourceName);
            $this->setArticles($articles);
        }
        catch(Exception $e){
            die('Erreur : ' . $e->getMessage());
        }
    }

    public function appendDatabase(string $bdd,string $user, string $pwd,string $bddName):void{
        $this->db = new DatabaseUtils($bdd,$user,$pwd,$bddName);
        $articles = $this->db->getArticlesFromDatabase();
        $this->setArticles($articles);
    }

    private function setArticles(Array $articles){
        $this->allArticles = array_merge($this->allArticles,$articles);
    }

    public function __construct() {
        $this->position = 0;
    }

    public function rewind(): void {
        $this->position = 0;
    }

    public function current() {
        return $this->allArticles[$this->position];
    }

    public function key() {
        return $this->position;
    }

    public function next(): void {
        ++$this->position;
    }

    public function valid(): bool {
        return isset($this->allArticles[$this->position]);
    }
    
}

$a = new ArticleAgregator();

/**
 * Récupère les articles de la base de données, avec leur source.
 * host, username, password, database name
 */
$a->appendDatabase('localhost', 'khalil', 'root', 'article_agregator');

/**
 * Récupère les articles d'un flux rss donné
 * source name, feed url
 */
$a->appendRss('Le Monde',    'http://www.lemonde.fr/rss/une.xml');
foreach ($a as $article) {
    echo sprintf('<h2>%s</h2><em>%s</em><p>%s</p>',
        $article->name,
        $article->sourceName,
        $article->content
    );
}
