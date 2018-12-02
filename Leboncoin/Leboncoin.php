<?php

namespace Absmoca;

/**
* API LEBONCOIN PHP
*
* @author     Cauquil Thomas <contact@thomascauquil.fr> @absmoca
* @version    1.1
* 
*/

class Leboncoin{
	

	/** @var string 	URL DE L'API LEBONCOIN */
	const URL_BASE 	= "https://api.leboncoin.fr/";

	/** @var string 	CLÉ DE L'API */
	const API_KEY 	= "ba0c2dad52b3ec";

	/** @var integer NOMBRE DE RÉSULTATS MAX PAR RECHERCHE */
	private static $LIMITE_RESULTATS = 100;


	/**
	 * Permet de changer le nombre de résultats obtenus par requete
	 * @param integer $nbr
	 */
	public static function nombreResultats($nbr){
		SELF::$LIMITE_RESULTATS = $nbr;
	}

	/**
	 * Permet la recherche d'annonces via parametres
	 * @param array $params (query, title_only, category, location, sortby, (particuliers|professionnels))
	 * ['query'] string: Nom de la recherche
	 * ['title_only'] boolean: Recherche uniquement "query" dans le titre
	 * ['category'] integer: ID de la catégorie de l'objet via searchCategory
	 * ['location'] array|array of object: Localisation(s) des annonces via searchLocation
	 * ['sortby'] array(price|time => asc, desc) : Tri les résultats
	 * ['particuliers'] boolean(false) : Masque les annonces de particuliers,
	 * ['professionnels'] boolean(false) : Masque les annonces de professionnels
	 * @param integer $page Numéro de la page
	 * @return Object
	 */
	public static function getAnnonces($params, $page = 0){
		$result = SELF::callApi("finder/search", SELF::filterMapGetAnnonces($params, $page));
		$annonces = array("total" => $result->total, "annonces" => array());
		if(!isset($result->ads) || count($result->ads) == 0) return false;
		foreach ($result->ads as $k => $a) {
			if($k == 0 && count($result->ads) > 1) continue;
			$a = new Annonce($a, true);
			$annonces['annonces'][] = $a;
		}
		return (Object) $annonces;
	}

	/**
	 * Permet de rechercher les annonces d'un utilisateur spécifique
	 * @param  string  $userid ID de l'utilisateur
	 * @param  boolean|array $params Parametres donnés à la recherche (voir getAnnonces)
	 * @param  integer $page   Numéro de le page
	 * @return Object          Retourne le résultat de getAnnonces
	 */
	public static function getAnnoncesUser($userid, $params = false, $page = 0){
		if($params && is_array($params)){
			$params['user_id'] = $userid;
		}else $params = array('user_id' => $userid);
		return SELF::getAnnonces($params, $page);
	}

	/**
	 * @param  array $params Formate les paramètres en json
	 * @return string
	 */
	public static function filterMapGetAnnonces($params, $page){
		$post = array("limit" => SELF::$LIMITE_RESULTATS, "limit_alu" => 3, "filters" => array());

		if(isset($params['query'])) $post['filters']['keywords']['text'] = htmlspecialchars($params['query']);
		if(isset($params['title_only']) && $params['title_only'] == true){
			$post['filters']['keywords']['type'] = "subject";
		}

		if(isset($params['context'])) $post['context'] = 'default';

		if(isset($params['store_id'])) $post['filters']['owner']['store_id'] = $params['store_id'];
		if(isset($params['user_id'])) $post['filters']['owner']['user_id'] = $params['user_id'];

		if(isset($params['category'])){
			$post['filters']['category'] = (Object) array('id' => (string) $params['category']);
		}else $post['filters']['category'] = (Object) array();

		if(isset($params['location'])){
			if(is_array($params['location'])){
				$l = array();
				$z = array();
				foreach ($params['location'] as $ll) {
					if($ll->zipcode) $z[] = (Object) array("zipcode" => (string) $ll->zipcode);
				}
				if(count($z) > 0){
					$l['city_zipcodes'] = $z;
				}else $l["regions"] = array((string) $params['location'][0]->region_id);
			}else{
				$l = array("regions" => array((string) $params['location']->region_id));
				if($params['location']->zipcode) $l['city_zipcodes'] = array((Object) array("zipcode" => (string) $params['location']->zipcode));
			}
		}else $l = (object) array();
		$post['filters']['location'] = $l;

		if(isset($params['sortby'])){
			$post['sort_by'] = key($params['sortby']);
			$post['sort_order'] = $params['sortby'][$post['sort_by']];
		}

		if(isset($params['particuliers']) && $params['particuliers'] == false){
			$post['owner_type'] = "pro";
		}elseif(isset($params['professionnels']) && $params['professionnels'] == false){
			$post['owner_type'] = "private";
		}

		//$post['ranges'] = array();

		$post['offset'] = ($post['limit'] * $page);		
		return json_encode($post);
	}

	/**
	 * Rechercher une annonce via son ID depuis la classe Annonce
	 * @param  integer $id ID de l'annonce
	 * @return object
	 */
	protected static function getAnnonce($id){
		return SELF::callApi("finder/classified/" . $id);
	}

	/**
	 * Construit l'appel de l'API
	 * @param  string $base Repertoire de l'API
	 * @param  string $post Données de la recherche
	 * @return object
	 */
	protected static function callApi($base, $post = false){
		$a = json_decode(SELF::curl(SELF::URL_BASE . $base, $post));
		return (json_last_error()==JSON_ERROR_NONE)?$a:false;
	}

	/**
	 * Construit l'appel de l'API avec un access authentifié
	 * @param  string $base Repertoire de l'API
	 * @param  string $access Token d'utilisateur
	 * @param  string $post Données de la recherche
	 * @return object
	 */
	protected static function callApiLogged($base, $access, $post = false){
		$a = json_decode(SELF::curl(SELF::URL_BASE . $base, $post, $access));
		return (json_last_error()==JSON_ERROR_NONE)?$a:false;
	}

	/**
	 * Récupère les catégories des annonces dans le fichier categories.json
	 * @return array Liste des catégories
	 */
	public static function categories(){
		$c = json_decode(file_get_contents(__DIR__ . '/categories.json'));
		$cats = array();
		foreach ($c as $v) {
			$cats[$v->id] = $v->name;
			if(isset($v->subcategories) && isset($v->subcategories[0])){
				foreach ($v->subcategories as $vv) {
					$cats[$vv->id] = $vv->name;
				}
			}
		}
		ksort($cats);
		return $cats;
	}

	/**
	 * Récupère l'id de la catégorie par rapport à son nom
	 * @param  string $n Nom de la categorie
	 * @return object
	 */
	public static function searchCategory($n){
		$res = array("perc" => 0, "id" => null, "name" => null);
		foreach (SELF::categories() as $id => $name) {
			similar_text(SELF::simpleText($name), SELF::simpleText($n), $p);
			if($p > $res['perc']){
				$res = array("perc" => $p, "id" => $id, "name" => $name);
			}
		}
		return (object) $res;
	}


	/**
	 * Recherche un lieu par son nom
	 * @param string $n Lieu à rechercher
	 * @param boolean $precis Si vrai récupère seulement le premier résultat de façon précise (pas la région)
	 * @return array of object(city, zipcode, region_id, department_id, label)
	 */
	public static function searchLocation($n, $precis = false){
		$post = '{"text":"'.$n.'","context":{}}';
		$r = SELF::callApi("api/parrot/v1/cityzipcode/complete", $post);
		if(!is_array($r) || count($r) == 0) return false;
		if(!$precis){
			return $r;
		}else{
			if(count($r) > 1 && preg_match('#(toute la ville)#i', $r[0]->label)){
				return $r[1];
			}else return $r[0];
		}
	}

	/**
	 * Récupère les endroits des annonces dans le fichier locations.json
	 * @return array of object(id, name)
	 */
	public static function locations(){
		$c = json_decode(file_get_contents(__DIR__ . '/locations.json'));
		return $c;
	}


	/**
	 * Supprime tous les caractères spéciaux
	 * @param  string $str Chaine à utiliser
	 * @return string
	 */
	private static function simpleText($str){
		return trim(preg_replace('#[^a-z0-9]#', '', mb_strtolower(urldecode($str),'UTF-8')));
	}

	/**
	 * Récuperer des données via une requete HTTP
	 * @param  string $url Lien où l'on vas récuperer les données
	 * @param  (string|boolean) $post Si des données POST seront à envoyer
	 * @param  (string|boolean) $access Pour donner un token d'access
	 * @param  (string|boolean) $cookie Si des données COOKIE seront à envoyer
	 * @param  boolean $cache Spécifie le controle du cache
	 * @return string
	 */
	private static function curl($url, $post = false, $access = false, $cookie = false, $cache = false){
		$ch = curl_init(); 
	    curl_setopt($ch, CURLOPT_URL, $url);
	    if($post != false){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
	    if($cookie != false) curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	    $headers = array();
	    if(!$cache) $headers[] = "Cache-Control: no-cache"; 
	    
	    $ip = (isset($_SERVER["HTTP_CF_CONNECTING_IP"]))?$_SERVER["HTTP_CF_CONNECTING_IP"]:$_SERVER['REMOTE_ADDR'];

	    $headers[] = "REMOTE_ADDR: ".$ip;
	    $headers[] = "HTTP_X_FORWARDED_FOR: ".$ip;
		$headers[] = 'api_key: '. SELF::API_KEY;

		if($access) $headers[] = 'Authorization: Bearer '. $access;

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	    curl_setopt($ch, CURLOPT_USERAGENT,'Leboncoin/3.16.1 (iPhone; iOS 10.0; Scale/2.00)');
		curl_setopt($ch, CURLOPT_REFERER, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
	    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 1
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	    $output = curl_exec($ch); 

	    curl_close($ch); 
	    return $output;
	}

}

?>