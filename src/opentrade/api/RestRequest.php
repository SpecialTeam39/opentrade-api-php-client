<?php

namespace OpenTrade\Api;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use function OpenTrade\Helpers\is_empty;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use InvalidArgumentException;
use \Illuminate\Support\Facades\Log as Log ;
use \Illuminate\Support\Facades\Cache as Cache ;

/**
 * Class RestRequest
 *
 * @author jordy <jordy.fatigba[at]theopentrade.com>
 * @package OpenTrade\Api
 * @version v0.0.0
 */
class RestRequest
{
    /**
     * Les types de corps permis pour une requête PUT/POST.
     */
    const BODY_TYPES = ["multipart", "form_params", "json"] ;

    /**
     * Configuration de l'API REST.
     *
     * @var ApiConfigInterface
     */
    protected $appConfig ;

    /**
     * Client HTTP custom.
     * <p>Il ne lance pas d'exception en cas de réponses négatives du serveur.</p>
     *
     * @var HttpClient
     */
    protected $httpClient ;

    private function __construct(ApiConfigInterface $config, array $headers = [])
    {
        $this->appConfig = $config ;
        $this->httpClient = new HttpClient(
            [
                "base_uri" => $this->appConfig->getUrlRest(),
                "http_errors" => FALSE,
                "decode_content" => TRUE,
                "headers" => $headers
            ]
        ) ;
    }


    /**
     * Cette fonction permet d'abonner/ de désabonner un client à un vendeur.
     *
     * @param string $user_id L'identifinat du client
     * @param string $trader_id L'identifiant du vendeur
     * @param bool $notify Boolean pour recevoir des notifications
     *
     * @return boolean true pour l'abonnement, false pour le désabonnement.
     *
     * @throws GuzzleException
     * @throws RestRequestException
     */
    public function follow(string $user_id, string $trader_id, bool $notify)
    {
        $data = [
            "customer_id" => $user_id,
            "trader_id" => $trader_id,
            "notify" => $notify,
        ] ;

        $path = "/user/subscribe/{$user_id}"   ;

        $request = $this->post($path, self::BODY_TYPES[1], $data, [
                "access_token" => $this->getAccessToken()
        ]) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;

        if($response["code"] != 2000 && $response["code"] != 2001)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["code"] == 2000 ;
    }

    /**
     * Apprécier (j'aime/je n'aime pas) un produit.
     *
     * @param string $item_id L'identifiant du produit.
     * @param string $user_id L'identifiant de l'utilisateur.
     * @param bool $value true pour j'aime ou false pour je n'aime pas.
     *
     * @return array La clé data de la réponse du web service.
     *
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function like(string $item_id, string $user_id, $value)
    {
        $data = [
            "user_id"=>$user_id,
            "like"=>$value,
        ] ;

        $path = "/items/{$item_id}/like" ;

        $request = $this->put($path, self::BODY_TYPES[1], $data,
            [
                "access_token"=>$this->getAccessToken(),
            ]) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;

        if($response["code"] != 2000 && $response["code"] != 2001)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Récupérer les stats de like d'un produit.
     *
     * @param string $item_id L'id du produit.
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function likes(string $item_id)
    {
        $path = "/items/{$item_id}/like" ;

        $request = $this->get($path, ["client_id"=>$this->appConfig->getClientId(), "access_token"=>$this->getAccessToken()]) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;

        if($response["code"] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            return $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Récupérer les informations d'un utilisateur pour le connecter à l'application cliente.
     *
     * @param string $login son nom d'utilisateur ou son email.
     * @param string $password son mot de passe en clair.
     *
     * @return array
     *
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function login(string $login, string $password)
    {
        if(is_null($login) || is_null($password)) {
            throw new InvalidArgumentException("login or password is null", 400) ;
        }

        if(strlen($login) <0 || strlen($password) < 0) {
            throw new InvalidArgumentException("", 400) ;
        }

        $request = $this->post("/user/signin", "form_params", [
                'login' => $login,
                'password' => $password
            ],
            ['access_token' => $this->getAccessToken()]
        );

        $response = json_decode($request->getBody()->getContents(), TRUE) ;
        if($response["code"] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Récupérer les abonnements ou les abonnés d'un utilisateur.
     *
     * @param string $user_id L'id de utilisateur.
     * @param string $type Doit prendre les valeurs 'followers' ou 'following'
     * @param int $limit Le nombre maximal d'occurence.
     * @param int $offset Le numéro de pagination à partir de zéro.
     *
     * @return mixed
     *
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function getFollowersOrSubcription(string $user_id, string $type, int $limit, int $offset=0)
    {
        if($user_id == null || $user_id == "") {
            throw new InvalidArgumentException("") ;
        }

        if(strcmp($type, "following") != 0 && strcmp($type, "followers") != 0)
        {
            throw new InvalidArgumentException("type route parameter must be equal to following or followers, with case sensitive") ;
        }

        $query = [
            'offset' => $offset,
            'limit' => $limit,
            'access_token' => $this->getAccessToken(),
        ] ;

        $request= $this->get("/user/{$user_id}/{$type}",$query) ;
        $response = json_decode($request->getBody()->getContents(), TRUE) ;
        if($response['code'] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Récupérer l'ensemble des catégories de produit.
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function getCategories()
    {
        $access_token = $this->getAccessToken() ;
        if(!Cache::has("categories"))
        {
            Log::info("tentative de récupération des catégories") ;
            $response = $this->get("/categories",[
                "access_token" => $access_token
            ]);

            $categories = json_decode((string) $response->getBody(), TRUE) ;

            if ($categories["code"] != 2000)
            {
                Log::error($categories["data"]["debug"]) ;
                return [] ;
            }
            Cache::put("categories", $categories["data"],1440) ;
        }
        return Cache::get("categories") ;
    }

    /**
     * Récupérer une catégorie avec son identifiant.
     *
     * @param string $category_id L'identifiant de la catégorie.
     *
     * @return mixed|null null si la catégorie n'existe pas.
     *
     * @throws GuzzleException
     */
    public function getCategory(string $category_id)
    {
        $categories = $this->getCategories() ;

        foreach ($categories as $category)
        {
            if(strcmp($category["id"], $category_id) == 0) {
                return $category ;
            }
        }

        return null ;
    }

    /**
     * Récupérer les paniers de produits d'un client.
     *
     * @param string $userId L'identifiant du client.
     * @param int $limit Le nombre panier à récupérer.
     * @param int $offset Le numéro de pagination à partir de zéro.
     *
     * @return array
     *
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function getBaskets(string $userId, int $limit, int $offset)
    {
        $response = $this->get("/baskets/user/{$userId}" ,
            [
                "access_token" => $this->getAccessToken(),
                "limit" => $limit,
                "offset" => $offset
            ]) ;

        $response = json_decode((string) $response->getBody(), TRUE) ;
        if ($response == null) {
            return [] ;
        }
        else
        {
            if($response["code"] != 2000)
            {
                $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
                throw $e->setDebugMessage($response["data"]["debug"]) ;
            }

            return $response["data"] ;
        }
    }

    /**
     * Récupérer le jetton d'accès à l'API REST.
     * En cas de succes le jetton est mis en cache pour 58 minutes.
     *
     * @return string Le jetton d'acces.
     *
     * @throws GuzzleException
     */
    public function getAccessToken()
    {
        if (!Cache::has("access_token"))
        {
            $grant = [
                "client_id" => $this->appConfig->getClientId(),
                "grant_type" => "client_credentials",
                "client_secret" => $this->appConfig->getSecret()
            ] ;
            $response = $this->post("/oauth/authorization", "form_params", $grant);

            if ($response->getStatusCode() != 200)
            {
                $e = new RestRequestException("Impossible de se connecter au serveur !", $response->getStatusCode()) ;
                return $e->setDebugMessage($response->getBody()->getContents()) ;
            }
            $access_token = json_decode((string)$response->getBody(), TRUE)["access_token"];
            Cache::add("access_token", $access_token, 58);
        }
        return Cache::get("access_token");
    }

    // region Gestion des commandes

    protected function getOrders(string $path, int $limit, int $offset, array $fields = [])
    {
        if($limit <= 0 || $offset < 0)
        {
            throw new InvalidArgumentException("limit ou offset doivent être >= 0") ;
        }

        $access_token = $this->getAccessToken() ;
        $request = $this->get("/orders/{$path}", [
            "access_token" => $access_token,
            "limit" => $limit,
            "offset" => $offset,
            "fields" => json_encode($fields)
        ]) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;

        if($response["code"] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Récupérer les commandes destinées à un vendeur.
     *
     * @param string $trader_id L'identifiant du vendeur.
     * @param int $limit Le nombre de commande à récupérer.
     * @param int $offset Le numéro de pagination à partir de zéro.
     *
     * @return mixed
     *
     * @throws RestRequestException
     */
    public function getTraderOrders(string $trader_id, int $limit, int $offset)
    {
        if($trader_id == "") {
            throw new InvalidArgumentException("l'identifiant ne doit pas être sans valeur") ;
        }

        $path = "traders/{$trader_id}" ;

        return $this->getOrders($path, $limit, $offset) ;
    }

    /**
     * Récupérer les commandes d'un client.
     *
     * @param string $customer_id L'identifiant du vendeur.
     * @param int $limit Le nombre de commande à récupérer.
     * @param int $offset Le numéro de pagination à partir de zéro.
     *
     * @return mixed
     *
     * @throws RestRequestException
     */
    public function getCustomerOrders(string $customer_id, int $limit, int $offset, array $fields)
    {
        if($customer_id == "") {
            throw new InvalidArgumentException("l'identifiant ne doit pas être sans valeur") ;
        }

        $path = "customers/{$customer_id}" ;

        return $this->getOrders($path, $limit, $offset, $fields) ;
    }

    /**
     * Récupérer les détails d'une commande principale, du point de vue d'un vendeur ou d'un acheteur.
     *
     * @param string $order_id L'identifiant de la commande.
     * @param string $customer_id L'identifiant du client.
     * @param string $trader_id L'identitiant du vendeur, utile, si c'est le vendeur qui fait la recherche.
     *
     * @return array
     *
     * @throws RestRequestException si le code http est différent de 200 ou 404.
     * @throws GuzzleException
     */
    public function getOneOrder(string $order_id, string $customer_id, string $trader_id = "")
    {
        $access_token = $this->getAccessToken() ;

        if($order_id == "" || $customer_id=="")
            throw new InvalidArgumentException("order_id ou customer_id est vide") ;

        $path = ($trader_id != "") ? "/orders/{$order_id}/traders/{$trader_id}/customers/{$customer_id}"
            : "/orders/{$order_id}/customers/{$customer_id}" ;

        $request = $this->get($path, [
            "access_token" => $access_token,
            "fields" => json_encode(["total", "id", "orderAlias", "status", "date"])
        ]) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;

        if($request->getStatusCode() == 404)
            return $response["data"]["message"] ;

        if($response["code"] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    // endregion

    /**
     * @param string $path La route au niveau du web service.
     * @param int $limit Le nombre maximal de produit à récupérer.
     * @param int $offset Le numéro de pagination à partir de 0.
     * @param string $fields Les champs de produits à récupérer; sous la forme d'une tableau json encodé.
     * @param array $orders Les critères de tri des produits, les valeurs admises sont reviews, price, quantity, addedAt.
     * @param array $queries Les paramettres de l'URL.
     * @return array
     * @throws RestRequestException Au cas ou le web service renvoie un code != 2000.*@throws GuzzleException
     * @throws GuzzleException
     * @todo check values in $orders parameter
     * Fonction principales pour récupérer une liste de produits.
     */
    private function getItemsMain(string $path, int $limit, int $offset, string $fields = "all", array $orders = ["reviews"], array $queries = [])
    {
        if($limit <= 0) {
            throw new InvalidArgumentException("limit must be greater than zero");
        }

        if($offset < 0) {
            throw new InvalidArgumentException("offset must be greater or equal to 1") ;
        }

        $access_token = $this->getAccessToken() ;
        $query = [
            'access_token'=>$access_token,
            "limit"=>$limit,
            "offset"=>$offset,
            "fields"=>$fields,
            'orderBy'=>join(",", $orders)
        ] ;

        $request = $this->get($path, array_merge($query, $queries)) ;

        $response = json_decode($request->getBody()->getContents(), TRUE);
        if($response["code"] != 2000)
        {
            // dd($response) ;
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Récupérer une liste de produits.
     * @param int $offset La page à partir de zéro
     * @param int $limit Le nombre de produit
     * @param array $orders
     * @param string $fields
     * @return array
     * @throws RestRequestException
     */
    public function getItems(int $offset, int $limit, string $fields = "all", array $orders = ["reviews"]){
        return $this->getItemsMain("/items", $limit, $offset, $fields, $orders) ;
    }

    /**
     * Récupérer une liste de produits avec des ids.
     * @param string $ids Les identifiants séparées par des virgules.
     * @param int $offset La page à partir de zéro
     * @param int $limit Le nombre de produit par page
     * @param string $fields
     * @param array $orders
     * @return array
     * @throws RestRequestException
     */
    public function getItemsByMultiplsIds(string $ids, int $offset, int $limit, string $fields = "all", array $orders = ["reviews"]){
        return $this->getItemsMain("/items/{$ids}", $limit, $offset, $fields, $orders) ;
    }

    /**
     * Récupérer un produit avec son id
     * @param string $item_id L'identifiant du produit
     * @param string $fields
     * @return mixed Un tableau associatif représentant le produit
     * @throws RestRequestException Si le code <> 2000
     * @throws GuzzleException
     */
    public function getItem(string $item_id, string $fields = "all")
    {
        if($item_id == null || strlen($item_id) != 24) {
            throw new InvalidArgumentException("invalid hexa representation of id") ;
        }

        $path = "/item/{$item_id}" ;
        $query= [
            'access_token'=>$this->getAccessToken(),
            'client_id'=>$this->appConfig->getClientId(),
            'fields'=>$fields
        ] ;

        $resquest = $this->get($path, $query) ;
        $response = json_decode($resquest->getBody()->getContents(), TRUE) ;
        if($response["code"] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Récupérer les produits d'un utilisateurs.
     * Cela se fait soit en appelant le web service ou en utilisant les données du cache.
     * @param string $user_id L'identifiant de l'utilisateur
     * @param int $limit
     * @param int $offset
     * @param string $fields
     * @param array $orders
     * @return mixed|null null s'il n'y a aucun produit ou les produits sous forme de tableau associatif.
     * @throws RestRequestException
     */
    public function getItemsByUserId(string $user_id, int $limit, int $offset, string $fields = "all", array $orders = ["reviews"])
    {
        if($user_id == null OR strlen($user_id) < 0) {
            throw new InvalidArgumentException("l'identifiant doit être non null et différent de \"\"") ;
        }

        return $this->getItemsMain("/items/user/{$user_id}", $limit, $offset, $fields, $orders) ;
    }

    /**
     * Récupérer les infos de boutiques enregistrées.
     *
     * @param int $limit Le nombre maximal d'entrée à récupérer
     * @param int $offset Le numéro de pagination à partir de zéro.
     * @return mixed
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function getShops(int $limit, int $offset)
    {
        if($limit <= 0) {
            throw new InvalidArgumentException(RestRequestException::LIMIT_ILLEGAL_ARG_MSG);
        }

        if($offset < 0) {
            throw new InvalidArgumentException(RestRequestException::OFFSET_ILLEGAL_ARG_MSG) ;
        }

        $accessToken = $this->getAccessToken() ;
        $path = "/user/shops" ;
        $response = $this->get($path, [
            "access_token" => $accessToken,
            "limit" => $limit,
            "offset" => $offset
        ]) ;

        $json = json_decode($response->getBody()->getContents(), true) ;

        if($response->getStatusCode() != 200) {
            throw RestRequestException::fromRestResponse($json) ;
        }

        return $json["data"] ;
    }

    // region Commentaires

    public function getCommentsByItemId(string $item_id, int $limit, $offset)
    {
        if($item_id == null || $item_id == "") {
            throw new InvalidArgumentException("invalid hexa representation of id") ;
        }
        if($limit <= 0) {
            throw new InvalidArgumentException(RestRequestException::LIMIT_ILLEGAL_ARG_MSG) ;
        }
        if($offset < 0) {
            throw new InvalidArgumentException(RestRequestException::OFFSET_ILLEGAL_ARG_MSG) ;
        }

        $path = "/comments/items/{$item_id}" ;
        $query= [
            'access_token'=>$this->getAccessToken(),
            'limit'=>$limit,
            'offset'=>$offset
        ] ;

        $resquest = $this->get($path, $query) ;
        $response = json_decode($resquest->getBody()->getContents(), TRUE) ;
        if($response["code"] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }


    /**
     * Enregistrer un nouveau commentaire
     *
     * @param string $user_id L'id de l'utilisateur s'il est inscrit.
     * @param string $item_id
     * @param string $comment_text
     * @param string $ano_name Le nom du commentateur s'il est anonyme
     * @param string $ano_email L'email du commentateur s'il est anonyme
     * @return mixed
     * @throws RestRequestException*@throws GuzzleException
     * @throws GuzzleException
     * @author jordy jordy.fatigba@theopentrade.com
     *
     */
    public function addComment(string $user_id, string $item_id, string $comment_text, string $ano_name = "", string $ano_email = "")
    {
        $request = $this->post("/comments/items/{$item_id}", "form_params",
            [
                'user_id' => $user_id,
                'comment_text' => $comment_text,
                'anonymous_name' => $ano_name,
                'anonymous_email' => $ano_email,
            ], [
                'access_token'=>$this->getAccessToken()
            ]
        ) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;
        if($response["status"] != "success")
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }
        return $response["data"] ;
    }

    public function updateComment(string $user_id, string $comment_id, string $comment_text)
    {
        $request = $this->put("/comments/user/{$user_id}/{$comment_id}", "form_params", [
            'comment_text' => $comment_text,
        ],
            [
                'client_id'=>$this->appConfig->getClientId(),
                'access_token'=>$this->appConfig->getAccessToken()
            ]
        ) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;
        if($response["status"] != "success")
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }
        return $response["data"] ;
    }

    // endregion

    // region Méthodes Http

    /**
     * Faire une requête HTTP::PUT
     *
     * @param string $path
     * @param string $body_type 'multipart', self::self::BODY_TYPES[1 ,'json'
     * @param array $data
     * @param array $query
     *
     * @return mixed|ResponseInterface
     *
     * @throws InvalidArgumentException si $body_type ne correspond à aucune valeur prédéfinie.
     * @throws GuzzleException
     */
    public function put(string $path, string $body_type, array $data, array $query = [])
    {
        if(!in_array($body_type, self::BODY_TYPES))
        {
            throw new InvalidArgumentException("body_type doit avoir une des valeurs suivante: multipart, form_params ou json") ;
        }
        $params["query"]=$query;
        $params[$body_type]=$data;
        //dd($params) ;
        return $this->httpClient->request("PUT", $path, $params) ;
    }

    /**
     * Faire une requête HTTP POST.
     *
     * @param string $path Une URI par rapport à l'URL de base de l'API REST.
     * @param string $body_type multipart, form_params ou json
     * @param array $data
     * @param array $query Les paramètres de l'URL
     *
     * @return mixed|ResponseInterface
     *
     * @throws GuzzleException
     */
    public function post(string $path, string $body_type, array $data, array $query = [])
    {
        $body_types = ['multipart', self::BODY_TYPES[1], 'json'] ;

        if(!in_array($body_type, $body_types)) {
            throw new InvalidArgumentException("body_type doit avoir une des valeurs suivante: multipart, form_params ou json") ;
        }

        $params["query"] = $query;
        $params[$body_type] = $data;

        return $this->httpClient->request("POST", $path, $params) ;
    }

    /**
     * Faire une requête HTTP DELETE.
     *
     * @param string $path L'URI par rapport à l'URL de base de l'API.
     * @param array $query Les paramètres de l'URL.
     *
     * @return mixed|ResponseInterface
     *
     * @throws GuzzleException
     */
    public function delete(string $path, array $query = [])
    {
        $params["query"] = $query;
        return $this->httpClient->request("DELETE", $path, $params);
    }

    /**
     * Faire une requête HTTP GET.
     *
     * @param string $path L'URI par rapport à l'URL de base de l'API.
     * @param array $query Les paramètres de l'URL.
     *
     * @return mixed|ResponseInterface
     *
     * @throws GuzzleException
     */
    public function get(string $path, array $query = [])
    {
        $params["query"] = $query;
        return $this->httpClient->request("GET", $path, $params);
    }

    // endregion

    /**
     * Supprimer un produit d'un utilisateur
     * @param string $user_id
     * @param string $item_id
     * @return mixed le champ data de la réponse du web service.
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function deleteItem(string $user_id, string $item_id)
    {
        if(($user_id == null || $user_id == "") || ($item_id == null || $item_id == "")) {
            throw new InvalidArgumentException("L'identifiant de l'utilisateur ou du produit est invalide") ;
        }

        $path = "/item/user/{$user_id}/{$item_id}" ;


        $request = $this->delete($path, ["access_token"=>$this->getAccessToken(), "client_id"=>$this->appConfig->getClientId()]);
        $response = json_decode($request->getBody()->getContents(), TRUE) ;
        if($response["code"] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * @param string $user_id
     * @param array $multipart_form_data
     * @param string $action Le lien possible vers lequel le produit sera disponible
     * @return mixed L'id du nouveau produit.
     * @throws RestRequestException Si l'enregistrement n'est pas fait.
     * @throws GuzzleException
     */
    public function addItem(string $user_id, array $multipart_form_data, string $action)
    {
        if($user_id == null || $user_id == "") {
            throw new InvalidArgumentException("user_id is empty or null") ;
        }

        $request = $this->post( "/items/user/{$user_id}", 'multipart', $multipart_form_data, [
            "access_token"=>$this->getAccessToken(),
            "action"=>$action
        ]) ;
        $response = json_decode($request->getBody()->getContents(), TRUE) ;
        if($response["code"] != 2001)
        {
            Log::error($response["data"]["debug"]) ;
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response['data'] ;
    }

    /**
     * Modifier un produit.
     *
     * @param string $user_id
     * @param string $item_id
     * @param array $form_data
     * @param string $action Le lien du produit modifié
     *
     * @return mixed L'id du nouveau produit.
     *
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function updateItem(string $user_id, string $item_id, array $form_data, string $action)
    {
        if($user_id == null || $user_id == "")
        {
            throw new InvalidArgumentException("user_id is empty or null") ;
        }
        if($item_id == null || $item_id == "")
        {
            throw new InvalidArgumentException("item_id is empty or null") ;
        }

        $request = $this->put("/items/user/{$user_id}/{$item_id}", "json", $form_data,[
            "access_token" => $this->getAccessToken(),
            "action" => $action,
        ]);
        $response = json_decode((string)$request->getBody(), TRUE);

        if ($response['status'] == "success") {
            // Retour vers la page précédente
            return $response['data'] ;
        }

        Log::error($response["data"]["debug"]) ;
        $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
        throw $e->setDebugMessage($response["data"]["debug"]) ;
    }

    /**
     * Valider le jetton d'activation du compte
     * @param string $token Le jetton
     * @return mixed Un tableau associatif des données de l'utilisateur
     * @throws InvalidArgumentException Le jetton vaut null ou de longueur nulle
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function confirmation(string $token)
    {
        if(is_null($token) || strlen($token) < 0)
        {
            throw new InvalidArgumentException("token is null or have zero length value");
        }
        $request = $this->post("/user/account/confirm", "form_params",
            [
                "token"=>$token
            ],
            [
                'access_token'=>$this->getAccessToken(),
                'client_id'=>$this->appConfig->getClientId()
            ]) ;

        $response = json_decode($request->getBody()->getContents(), true);
        if($response["status"] != "success")
        {
            throw new RestRequestException($response["data"]["message"], $response['code']) ;
        }

        return json_decode($response["data"], TRUE) ;
    }

    /**
     * Créer un compte d'utilisateur
     * @param array $multilpart_data
     * @throws RestRequestException
     * @throws GuzzleException
     * @deprecated
     */
    public function signup(array $multilpart_data, string $redirect_url)
    {
        //if(count($multilpart_data ) < 0)
        $rest_request = $this->post("/user/signup", "multipart", $multilpart_data,
            [
                "client_id"=>$this->appConfig->getClientId(),
                "access_token"=>$this->getAccessToken(),
                "redirect_url"=>$redirect_url
            ]) ;

        $response = json_decode($rest_request->getBody()->getContents(), TRUE);
        if($response["code"] != 2001)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }
    }

    /**
     * Créer un compte d'utilisateur
     * @param array $data un tableau de clé-valeur
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function register(array $data, string $redirect_url)
    {
        //if(count($multilpart_data ) < 0)
        $rest_request = $this->post("/user/register", "json", $data,
            [
                "client_id"=>$this->appConfig->getClientId(),
                "access_token"=>$this->getAccessToken(),
                "redirect_url"=>$redirect_url
            ]) ;

        $response = json_decode($rest_request->getBody()->getContents(), TRUE);
        if($response["code"] != 2001)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }
    }

    public function popularity($item_id, $what)
    {
        $path = "/items/{$item_id}/popularity" ;

        $request = $this->put($path, "form_params",
            ["what" => $what],
            ["access_token"=>$this->getAccessToken()]
        ) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;

        if($request->getStatusCode() != 204)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Récupérer des produits en fonction d'une catégorie
     * @param string $category_id L'identifiant de la catégories
     * @param int $offset
     * @param int $limit
     * @param string $fields
     * @param array $orders
     * @return mixed
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function getItemsByCategory(string $category_id, int $offset, int $limit, string $fields = "all", array $orders = ["reviews"]){
        return $this->getItemsMain("/category/{$category_id}/items", $limit, $offset, $fields, $orders) ;
    }

    /**
     * Fonction de recherche.
     * @param array $data Voir la doc du web service.
     * @return mixed
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function search(array $data)
    {
        $path = "/items/search";

        $request = $this->post($path, "json",
            $data,
            [
                'access_token' => $this->getAccessToken(),
                'client_id' => $this->appConfig->getClientId()
            ]
        );

        $response = json_decode($request->getBody()->getContents(), TRUE);
        if ($response["code"] != 2000) {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    /**
     * Enregistrer une commande sur le web service
     * @param string $user_id l'identifiant de l'acheteur
     * @param string $backend_url L'url du panneau d'administration de la page
     * de consultation des commandes du point de vue du vendeur, avec le paramettre
     * d'url name positionné.
     * @param array $body
     * @return string l'identifiant de la nouvelle commande enregistrée.
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function order(string $user_id , string $backend_url, array $body)
    {
        $request = $this->post("/orders/customers/{$user_id}", "json",
            $body,
            [
                "access_token"=>$this->getAccessToken(),
                "client_id"=>$this->appConfig->getClientId(),
                "backend_url"=>$backend_url
            ]
        ) ;

        $statusCode = $request->getStatusCode() ;
        $response = json_decode($request->getBody()->getContents(), true);

        if($statusCode == 201)
        {
            // $response['data'] is a string.
            return $response["data"] ;
        }
        if($statusCode == 204) {
            throw new RestRequestException("Les comptes d'utilisateurs des marchands sont inactifs !", $response["data"]["httpCode"]) ;
        }

        $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
        throw $e->setDebugMessage($response["data"]["debug"]) ;
    }

    // region Informations sur l'utilisateur

    private function updateUserPersonnalInfo(string $path, array $data)
    {
        $request = $this->put($path, "json", $data, [
            "access_token"=>$this->getAccessToken()
        ]) ;

        $response = json_decode($request->getBody()->getContents(), TRUE) ;
        if($response["code"] !== 2000) {
            RestRequestException::fromRestResponse($response) ;
        }

        return $response["data"] ;
    }

    public function updateShopInfo(array $shopInfo, string $userId) {
        return $this->updateUserPersonnalInfo("/user/{$userId}/shopinfo", $shopInfo) ;
    }

    public function updateProfile(array $userInfo, string $userId){
        return $this->updateUserPersonnalInfo("/user/{$userId}", $userInfo) ;
    }

    /**
     * Récupérer des infos sur un utilisateur.
     * @param string $login_or_id L'email ou l'id de l'utilisateur.
     * @param array $fields Un tableau des champs à récupérer.
     * @return mixed
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function getUserInfo(string $login_or_id, array $fields)
    {
        $path = "/user/{$login_or_id}" ;

        $request = $this->get($path, [
            "client_id"=>$this->appConfig->getClientId(),
            "access_token"=>$this->getAccessToken(),
            "fields"=>json_encode($fields)
        ]) ;
        $response = json_decode($request->getBody()->getContents(), TRUE) ;
        if($response["code"] != 2000)
        {
            $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
            throw $e->setDebugMessage($response["data"]["debug"]) ;
        }

        return $response["data"] ;
    }

    // endregion

    /**
     * Augmenter/diminuer la quantité d'un produit acheté dans un panier.
     * @param string $user_id
     * @param string $basket_id
     * @param string $purchase_id
     * @param int $value
     * @return array
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function increasePurchaseInBasket(string $user_id, string $basket_id, string $purchase_id, int $value)
    {
        $path = "/baskets/{$basket_id}/user/{$user_id}/purchases/{$purchase_id}" ;

        $request = $this->put($path, self::BODY_TYPES[1], ['value'=>$value],
            [
                "client_id"=>$this->appConfig->getClientId(),
                "access_token"=>$this->getAccessToken(),
            ]) ;

        $data = json_decode($request->getBody()->getContents(), TRUE) ;

        if($data["code"] != 2000)
        {
            $e = new RestRequestException($data["data"]["message"], $data["data"]["httpCode"]) ;
            throw $e->setDebugMessage($data["data"]["debug"]) ;
        }

        return $data["data"] ;
    }

    /**
     * Récupérer les moyens de paiement disponible.
     * @return array Un tableau contenant pour chaque paiement
     *          un id et une description ou un tableau vide en cas d'échec.
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function getPayments()
    {
        if(!Cache::has("payments"))
        {
            $path= "/payments";
            $query = [
                'access_token'=>$this->getAccessToken(),
                'client_id'=>$this->appConfig->getClientId()
            ] ;

            $request = $this->get($path,$query);

            $response = json_decode($request->getBody()->getContents(), true);
            if($response["status"] != "success")
            {
                Log::error($response["data"]["debug"]) ;
                return [] ;
            }
            Cache::put("payments", $response["data"], 1440) ;
        }
        return Cache::get("payments") ;
    }

    public function userStats(string $user_id, array $params)
    {
        if($user_id == null || strlen($user_id) == 0)
        {

        }
        if(count($params) == 0)
        {

        }

        $path = "/user/{$user_id}/stats" ;
        $query = array_merge([
            "access_token"=>$this->getAccessToken(),
            "client_id"=>$this->appConfig->getClientId(),
        ], $params);

        $response = $this->get($path, $query) ;

        $rest_response = json_decode($response->getBody()->getContents(), TRUE) ;
        //dd($response) ;
        if($response->getStatusCode() == 204)
        {
            return [] ;
        }
        if($response->getStatusCode() == 200)
        {
            return $rest_response["data"];
        }

        $exception = new RestRequestException($rest_response["data"]["message"], $rest_response["data"]["httpCode"]) ;

        throw $exception->setDebugMessage($rest_response["data"]["debug"]) ;
    }

    /**
     * Rechercher des commandes dans l'historique des commandes.
     * @param string $user_id user_id L'identifiant de l'utilisateur qui fait la recherche.
     * @param array $form_params Le formulaire de recherche.
     * @param int $limit Le nombre de commandes dans la réponse.
     * @param int $offset Le numéro de pagination à partir de 0
     * @return array
     * @throws RestRequestException si le code http est différent de 200 et 204.
     * @throws GuzzleException
     */
    public function searchOrders(string $user_id, array $form_params, int $limit, int $offset = 0)
    {
        if($user_id == "")
        {
            Log::error("user_id is empty") ;
            throw new InvalidArgumentException("Votre identifiant semble en vacance !") ;
        }

        $path = "/orders/search/{$user_id}" ;

        $request = $this->post($path, self::BODY_TYPES[1], $form_params, [
            "access_token"=>$this->getAccessToken(),
            "client_id"=>$this->appConfig->getClientId(),
            "limit"=>$limit,
            "offset"=>$offset,
        ]) ;

        $httpStatus = $request->getStatusCode()  ;
        $response = json_decode($request->getBody()->getContents(), TRUE) ;

        if($httpStatus == 200)
        {
            return $response['data'] ;
        }

        if($httpStatus == 204 || $httpStatus == 404)
        {
            return [] ;
        }

        $exception = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;

        throw $exception->setDebugMessage($response["data"]["debug"]) ;
    }

    /**
     * <p>Cette fonction appelle le web service pour demander une recommandation de produits pour un utilisateur</p>
     * @param string $type
     * @param string $user_id
     * @param int $limit
     * @param int $offset
     * @param string $fields
     * @return array|mixed Un tableau de produits en cas de succès et un tableau vide en cas de http 204.
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function recommendations(string $type, string $user_id, int $limit, int $offset, string $fields = "all")
    {
        if(strcmp($type, "collaborative") != 0 && strcmp($type, "content") != 0) {
            throw newInvalidArgumentException("type peut prendre uniquement les valeurs suivantes: content ou collaborative") ;
        }
        if(strlen($user_id) < 0) {
            throw new InvalidArgumentException("Votre identifiant semble en vacance !") ;
        }
        if($limit <= 0) {
            throw new InvalidArgumentException(RestRequestException::LIMIT_ILLEGAL_ARG_MSG) ;
        }
        if($offset < 0) {
            throw new InvalidArgumentException(RestRequestException::OFFSET_ILLEGAL_ARG_MSG) ;
        }

        $path = "/recommendation/{$user_id}/{$type}/" ;

        $response = $this->get($path, [
            "access_token"=>$this->getAccessToken(),
            "limit"=>$limit,
            "offset"=>$offset,
            "maxIter"=>2,
            "fields"=>$fields
        ]) ;

        $jsonBody = json_decode($response->getBody()->getContents(), true) ;
        $httpStatus = $response->getStatusCode() ;
        switch ($httpStatus)
        {
            case 200:
                return $jsonBody["data"] ;
            case 204:
                return [] ;
        }
        $exception = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;

        throw $exception->setDebugMessage($response["data"]["debug"]) ;
    }

    /**
     * <p>
     * Récupérer une liste de produits recommandés pour un utilisateur.
     * Il s'agit d'une recommandation basée sur les préférences.
     * </p>
     * <p> En cas d'échec de la requête une, on appelle simplement la liste des produits sans chichi.</p>
     * @param string $userId
     * @param int $offset La page à partir de zéro
     * @param int $limit Le nombre de produit
     * @param string $fields
     * @return mixed [ 'items': [[item entity], [item entity], ...], 'count':[int] ]
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function getPreferencedItems(string $userId , int $offset, int $limit, string $fields = "all"){
        return $this->recommendations("content", $userId, $limit, $offset, $fields);
    }

    // region SimpleSocialNetworkConnection

    /**
     * Demander le lien d'authenfication d'un réseau social pour un utilisateur.
     * @param string $social_network Le nom du réseau social.
     * @param string $callback_url
     * @return string Un lien ou # en cas d'échec.
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function getAuthenticationUrl(string $social_network, string $callback_url)
    {
        if (strlen($social_network) <= 0) {
            throw new InvalidArgumentException("please pass a non empty string as argument") ;
        }

        $path = "/social/{$social_network}";
        $response = $this->get($path, [
            "access_token" => $this->getAccessToken(),
            "callback_url" => $callback_url
        ]) ;

        if($response->getStatusCode() != 200) {
            return "#" ;
        }

        return $response->getBody()->getContents() ;
    }

    /**
     * @param string $social_network
     * @param string $user_id
     * @param string $oauth_verifier
     * @param string $oauth_token
     * @return bool
     * @throws RestRequestException*@throws GuzzleException
     * @throws GuzzleException
     * @todo doc
     */
    public function updateSocialNetworkAccessToken(string $social_network, string $user_id, string $oauth_verifier, string $oauth_token)
    {
        $path = "/social/{$social_network}/users/{$user_id}/tokens" ;
        $response = $this->post(
            $path, "form_params",
            [
                "oauth_token" => $oauth_token,
                "oauth_verifier" => $oauth_verifier,
            ],
            [
                "access_token" => $this->getAccessToken()
            ]
        ) ;

        $statusCode = $response->getStatusCode() ;

        if($statusCode != 200 && $statusCode != 204)
        {
            Log::warning("App\Utils\Net\RestRequest#updateSocialNetworkAccessToken: http status {$statusCode}") ;
            Log::warning("App\Utils\Net\RestRequest#updateSocialNetworkAccessToken: response body {$response->getBody()->getContents()}") ;
            return false ;
        }

        return true ;
    }

    /**
     * Modifier le message de publication des produits sur les réseaux sociaux.
     * @param string $user_id L'identifiant de l'utilisateur Open Trade
     * @param string $social_network Le réseau social(facebook, twitter, ...)
     * @param string $type new pour le message d'ajout de produit, promotion pour le message de promotion
     * @param string $templateMessage Le message de template
     * @return mixed
     * @throws RestRequestException
     * @throws GuzzleException
     */
    public function updateSocialNetworkTemplateMessage(string $user_id, string $social_network, string $type, string $templateMessage)
    {
        $path = "/social/{$social_network}/templates/{$user_id}/$type" ;

        $response = $this->put($path, "form_params", ["template" => $templateMessage], ["access_token" => $this->getAccessToken()]) ;

        $statusCode = $response->getStatusCode() ;
        if($statusCode != 200)
        {
            $e = new RestRequestException($response->getReasonPhrase(), $statusCode) ;
            $e->setDebugMessage($response->getBody()->getContents()) ;
        }

        return json_decode($response->getBody()->getContents(), true)["data"] ;
    }

    // endregion

    /**
     * Contacter un vendeur par email.
     * @param string $trader_id L'id du vendeur.
     * @param ContactForm $contactForm Le formulaire de contact.
     * @return mixed Un message de succès en cas de succès
     * @throws RestRequestException En cas d'échec
     * @throws GuzzleException
     */
    public function contactByEmail(string $trader_id, ContactForm $contactForm)
    {
        if(is_null($contactForm)) {
            throw new InvalidArgumentException("contact form is null") ;
        }

        if(is_empty($trader_id)) {
            throw new InvalidArgumentException("empty trader_id") ;
        }

        $path = "/user/{$trader_id}/contact/email";
        $query = [
            "access_token" => $this->getAccessToken()
        ] ;

        $response = $this->post($path, "form_params", [
            "subject" => $contactForm->getSubject(),
            "message" => $contactForm->getMessage(),
            "from_email" => $contactForm->getFromEmail(),
            "from_name" => $contactForm->getFromName()
        ], $query) ;

        $responseBody = json_decode($response->getBody()->getContents(), true) ;

        if($responseBody["data"]["httpCode"] != 202)
        {
            $exception = new RestRequestException($responseBody["data"]["message"], $responseBody["data"]["httpCode"]) ;

            throw $exception->setDebugMessage($responseBody["data"]["debug"]) ;
        }

        return $responseBody["data"]["message"] ;
    }
}

/**
 * Exception représantant une erreur grave survenue lors de l'utilisation
 * du web service.<br/>
 * Comme par exemple, l'impossibilité de récupérer un jetton d'accès.
 * @package App\Utils\Net
 */
class RestRequestException extends \Exception
{
    const LIMIT_ILLEGAL_ARG_MSG = "limit must be greater than zero" ;
    const OFFSET_ILLEGAL_ARG_MSG = "offset must be greater or equal to 1" ;
    /**
     * @var string Le message de debbogage pour les devs.
     */
    protected $debug ;

    /**
     * RestRequestException constructor.
     * @param string $message Le message à destination de l'utilisateur Humain.
     * @param int $code Un code Http
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        return $this ;
    }

    public function setDebugMessage($debug)
    {
        $this->debug = $debug ;
        return $this ;
    }

    public function getDebugMessage()
    {
        return $this->debug ;
    }

    public static function fromRestResponse(array $response)
    {
        $e = new RestRequestException($response["data"]["message"], $response["data"]["httpCode"]) ;
        throw $e->setDebugMessage($response["data"]["debug"]) ;
    }
}
