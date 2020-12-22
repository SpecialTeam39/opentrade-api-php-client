<?php


namespace OpenTrade\Api;

/**
 * Cette classe contient uniquement des méthode à implémenter pour obtenir les crédentials de l'API.
 *
 * @author jordy <jordy.fatigba[at]theopentrade.com>
 *
 * Class ApiConfig
 * @package OpenTrade\Api
 * @version v0.0.0
 */
interface ApiConfigInterface
{

    /**
     * Renvoie La clé d'API de l'application cliente.
     *
     * @return string
     */
    public function getSecret() : string ;


    /**
     * Renvoie l'URL d'authentification de l'API.
     *
     * @return string
     */
    public function getUrlAuth() : string ;

    /**
     * Renvoie L'URL de base de l'api
     *
     * @return string
     */
    public function getUrlRest() : string ;

    /**
     * Renvoie l'identifiant de l'application cliente
     *
     * @return string
     */
    public function getClientId() : string ;
}