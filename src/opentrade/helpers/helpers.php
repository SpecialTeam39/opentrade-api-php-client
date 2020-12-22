<?php
/**
 * Created by PhpStorm.
 * User: jordy
 * Date: 29/12/18
 * Time: 03:30
 */

namespace OpenTrade\Helpers ;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

if(!function_exists("share_link"))
{
    /**
     * Génère un lien de partage pour une plateform de réseaux social ou pour un email.
     * @param string $plateform telegram, facebook, whatsapp ou twitter
     * @param string $link
     * @param string $title
     * @return string
     */
    function share_link(string $plateform, string $link, string $title)
    {
        $url = rawurlencode($link) ;
        switch ($plateform)
        {
            case "telegram":
                return "https://telegram.me/share/url?url={$url}&text={$title}" ;
            case "facebook":
                return "https://www.facebook.com/sharer/sharer.php?u={$url}" ;
            case "email":
                return "mailto:foo@example.com?subject={$title}&body={$url}" ;
            case "whatsapp":
                return "whatsapp://send?text={$title} {$url}" ;
            case "twitter":
                return "https://twitter.com/intent/tweet?url={$url}&text={$title}&hashtags=opentrade" ;
        }
        return "" ;
    }
}

if(!function_exists("social_link"))
{
    /**
     * Imprime du code HTML pour avoir des icones de partage sur les réseaux sociaux.
     * Facebook, twitter, telegram ou email.
     * @param string $link Le lien à partager.
     * @param string $text Le text à ajouter au lien.
     * @param array $plateform Un tableau contenant pour chaque ligne le nom de la plateforme et la classe css de son icône
     *          et la classe css du lien. Les plateformes sont facebook,telegram et twitter et même email.
     * ex = [
     *        ["facebook", "fab fa-facebook", "btn-primary"],
     *        ["telegram", "fab fa-telegram", "btn-primary"]
     *      ]
     * @param string $tag
     * @param string $tagClass
     * @return string
     */
    function social_link(string $link, string $text, array $plateform, string $onclick="", string $tag = "div", string $tagClass = "mb-top-1")
    {
        $begin = "<{$tag} class=\"{$tagClass}\">" ;
        $end = "</{$tag}>" ;
        $_ = "" ;
        if(count($plateform) <= 0) {
            return $_ ;
        }
        else
        {
            foreach ($plateform as $p)
            {
                $token = csrf_token() ;
                $_ .= '<a onclick="pop({_token:\''.$token.'\', what:\'sharing\', plateform:\''.$p[0].'\'})" target="_blank" href="'.share_link($p[0], $link, $text).'" title="partager sur '.$p[0].' " class="btn '.$p[2].'"><i class="'.$p[1].'"></i></a> <b></b>' ;
            }
        }

        return new HtmlString($begin.$_.$end) ;
    }
}

if(!function_exists("op_pagination"))
{
    /**
     * Cette fonction créer du code HTML pour de la pagination avec les liens
     * suivant,précédent,première,dernière et les numéros de pagination.
     * @param string $path chemin relatif à une URL: /path1/path2/path3
     * @param int $n_pages Le nombre maximale de page.
     * @param int $current Le numéro de la page courante.
     * @return string une chaine de caractère contenant du code HTML.
     */
    function op_pagination(string $path, int $n_pages, int $current)
    {
        if($path == "") {
            return "" ;
        }

        if($n_pages < 0) {
            return "" ;
        }

        if($current < 0 || $current > $n_pages) {
            return "" ;
        }

        $current_minus_1 = $current-1;
        $current_plus_1 = $current+1;
        $limit_inf = 2 ;
        $limit_sup = $n_pages ;

        $pagination = '<ul class="pagination">' ;
        $pagination .= '<li><a class="to-hide" title="allez à la première page" href="'.url($path).'/1">Première</a></li>' ;

        if($current <= 1) {
            $pagination .= '<li class="disabled"><a>Précedent</a></li>' ;
        }
        else {
            $pagination .= '<li><a rel="prev" class="link-loader" title="allez à la page précédente" href="'.url($path). '/'. $current_minus_1 .'">Précedent</a></li>' ;
        }

        // Si on a moins de 8 pages, afficher toute le pagination de 1 à n
        if($n_pages <= 8)
        {
            for($i = 1; $i<=$n_pages; $i++)
                $pagination .= '<li><a rel="nofollow" class="link-loader" title="allez à la page '.$i.'" href="'.url($path).'/'. $i .'">'.$i.'</a></li>' ;
        }
        // Sinon on va faire des points
        else
        {
            $pagination .= '<li><a class="to-hide link-loader" title="allez à la page 1" href="'.url($path). '/1">1</a></li>' ;
            $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page 2" href="'.url($path). '/2">2</a></li>' ;
            $pagination .= '<li class="disabled"><a class="to-hide">...</a></li>' ;

            if($current > $limit_inf+1 && $current < $limit_sup-1)
            {
                $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.$current_minus_1.'" href="'.url($path). '/'.$current_minus_1.'">'.$current_minus_1.'</a></li>' ;
                $pagination .= '<li class="active"><a rel="nofollow" class="to-hide" title="allez à la page '.$current.'">'.$current.'</a></li>' ;
                $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.$current_plus_1.'" href="'.url($path). '/'.$current_plus_1.'">'.$current_plus_1.'</a></li>' ;
                $pagination .= '<li class="disabled"><a class="to-hide">...</a></li>' ;
            }
            $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.($n_pages-1).'" href="'.url($path). '/'.($n_pages-1).'">'.($n_pages-1).'</a></li>' ;
            $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.$n_pages.'" href="'.url($path). '/'.$n_pages.'">'.$n_pages.'</a></li>' ;
        }
        if($current == $n_pages) {
            $pagination .= '<li class="disabled to-hide"><a>Suivant</a></li>' ;
        }
        else {
            $pagination .= '<li><a rel="next" class="link-loader" title="allez à la page suivante" href="'.url($path).'/'.($current+1).'">Suivant</a></li>' ;
        }

        $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la dernière page" href="'.url($path).'/'.$n_pages.'">Dernière</a></li>' ;

        $pagination .= '</ul>' ;

        return new HtmlString($pagination) ;
    }
}

if(!function_exists("op_route_pagination"))
{
    function op_route_pagination(string $routeName, array $parameters, string $paginationParameter, int $n_pages, int $current)
    {
        if($routeName == "") {
            return "" ;
        }

        if($n_pages < 0) {
            return "" ;
        }

        if($current < 0 || $current > $n_pages) {
            return "" ;
        }

        $current_minus_1 = $current-1;
        $current_plus_1 = $current+1;
        $limit_inf = 2 ;
        $limit_sup = $n_pages ;

        $pagination = '<ul class="pagination">' ;
        $pagination .= '<li><a class="link-loader to-hide" title="allez à la première page" href="'.route($routeName, array_merge($parameters, [$paginationParameter => 1])).'">Première</a></li>' ;

        if($current <= 1) {
            $pagination .= '<li class="disabled"><a>Précedent</a></li>' ;
        }
        else {
            $pagination .= '<li><a rel="prev" class="link-loader" title="allez à la page précédente" href="'.route($routeName, array_merge($parameters, [$paginationParameter => $current_minus_1])).'">Précedent</a></li>' ;
        }

        // Si on a moins de 8 pages, afficher toute le pagination de 1 à n
        if($n_pages <= 8)
        {
            for($i = 1; $i<=$n_pages; $i++) {
                $pagination .= '<li><a rel="nofollow" class="link-loader" title="allez à la page '.$i.'" href="'.route($routeName, array_merge($parameters, [$paginationParameter => $i])).'">'.$i.'</a></li>' ;
            }
        }
        // Sinon on va faire des points
        else
        {
            $pagination .= '<li><a class="to-hide link-loader" title="allez à la page 1" href="'.route($routeName, array_merge($parameters, [$paginationParameter => 1])).'">1</a></li>' ;
            $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page 2" href="'.route($routeName, array_merge($parameters, [$paginationParameter => 2])).'">2</a></li>' ;
            $pagination .= '<li class="disabled"><a class="to-hide">...</a></li>' ;

            if($current > $limit_inf+1 && $current < $limit_sup-1)
            {
                $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.$current_minus_1.'" href="'.route($routeName, array_merge($parameters, [$paginationParameter => $current_minus_1])).'">'.$current_minus_1.'</a></li>' ;
                $pagination .= '<li class="active"><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.$current.'">'.$current.'</a></li>' ;
                $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.$current_plus_1.'" href="'.route($routeName, array_merge($parameters, [$paginationParameter => $current_plus_1])).'">'.$current_plus_1.'</a></li>' ;
                $pagination .= '<li class="disabled"><a class="to-hide">...</a></li>' ;
            }
            $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.($n_pages-1).'" href="'.route($routeName, array_merge($parameters, [$paginationParameter => $n_pages-1])).'">'.($n_pages-1).'</a></li>' ;
            $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la page '.$n_pages.'" href="'.route($routeName, array_merge($parameters, [$paginationParameter => $n_pages])).'">'.$n_pages.'</a></li>' ;
        }
        if($current == $n_pages) {
            $pagination .= '<li class="disabled to-hide"><a>Suivant</a></li>' ;
        }
        else {
            $pagination .= '<li><a rel="next" class="link-loader" title="allez à la page suivante" href="'.route($routeName, array_merge($parameters, [$paginationParameter => $current_plus_1])).'">Suivant</a></li>' ;
        }

        $pagination .= '<li><a rel="nofollow" class="to-hide link-loader" title="allez à la dernière page" href="'.route($routeName, array_merge($parameters, [$paginationParameter => $n_pages])).'">Dernière</a></li>' ;

        $pagination .= '</ul>' ;

        return new HtmlString($pagination) ;
    }
}

if(!function_exists("cl_transformation_url"))
{
    /**
     * Cette fonction eclate des url de cloudinary pour pouvoir appliquer dessus des transformations.
     * @param string $imgurl L'url de l'image source provenant de cloudinary.
     * @param string $transformation La transformmation à appliquer, ex : w_128,h_128
     * @return string Une url de cloudinary
     */
    function cl_transformation_url(string $imgurl, string $transformation)
    {
        $lastIndexOfSlash = strrpos($imgurl, "upload") ;
        $split0 = "" ;
        $split1 = substr($imgurl, $lastIndexOfSlash+strlen('upload')) ;
        for($i = 0; $i<$lastIndexOfSlash; $i++) {
            $split0 .= $imgurl[$i] ;
        }
        $split0 .= 'upload/' ;

        return $split0.$transformation.$split1 ;
    }
}

if(!function_exists('item_link'))
{
    /**
     * Générer un lien propre SEO friendly pour un produit en utilisant la route Item.
     * @param string $itemWording Le libellé du produit.
     * @param string $categoryId L'id de la catégorie du produit.
     * @param string $id L'id du produit.
     * @return string
     */
    function item_link(string $itemWording, string $categoryId, string $id)
    {
        $wording = strtolower(str_replace(" ", "-", $itemWording)) ;
        $categories = RestRequest::getInstance()->getCategories() ;
        $category = "category" ;
        foreach ($categories as $c)
        {
            if($c['id'] == $categoryId)
            {
                $category = strtolower(str_replace(" ", "-", $c["description"])) ;
                break ;
            }
        }
        $category = strtolower(str_replace(" ", "-", $category)) ;
        return route("Item", [
            "wording" => $wording,
            "id" => $id,
            "category_name" => $category
        ]) ;
    }
}

if(!function_exists("whatsapp_contact_button"))
{
    /**
     * @deprecated use {@link whatsapp_shop_button}
     * @param string $intlContact
     * @param string $nonEscapedText
     * @return HtmlString
     */
    function whatsapp_contact_button(string $intlContact, string $nonEscapedText) : HtmlString
    {
        $href = "https://wa.me/{$intlContact}?text=".htmlentities($nonEscapedText);
        $aTag = "<a class='btn btn-success border-squared' rel='noopener' href='".$href."' target='_blank' title='Contacter le vendeur sur whastapp'>"
            ."<i class='fab fa-whatsapp'></i>"
            ."</a>";
        return new HtmlString($aTag);
    }
}

if(!function_exists("whatsapp_shop_button"))
{
    /**
     * Cette fonction sert à construire un lien HTML pour contacter le vendeur d'un produit sur WhatsApp
     *
     * @param bool $isMobile true si le site est consulté depuis un mobile.
     * @param string $intlContact Le numéro de téléphone avec l'indicatif.
     * @param string $url L'url du produit.
     * @param string $phrase La phrase à envoyer au vendeur.
     * @param string $innerText Le texte du lien
     * @return HtmlString
     */
    function whatsapp_shop_button(bool $isMobile, string $intlContact, string $url, string $phrase, string $innerText) : HtmlString
    {
        $text = htmlentities($phrase.' '.urlencode($url)) ;
        $href = $isMobile ? "https://wa.me/{$intlContact}?text={$text}": "https://web.whatsapp.com/send?phone={$intlContact}&text={$text}";
        $aTag = "<a class='btn btn-success border-squared' href='".$href."' target='_blank' title='".$innerText."'>"
            .$innerText.
            "</a>" ;
        return new HtmlString($aTag) ;
    }
}

if(!function_exists("isUserAgentMobile"))
{
    function isUserAgentMobile()
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'] ;
        return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)) ;
    }
}

if(!function_exists("required_star"))
{
    /**
     * Afficher l'étoile pour signifier qu'un champ de formulaire est obligatoire.
     * @param string $color La couleur de l'étoile, rouge par défaut.
     * @return HtmlString
     */
    function required_star(string $color = "red") {
        return new HtmlString("<i style='color: ".$color."'>*</i>") ;
    }
}

if(!function_exists("is_empty"))
{
    function is_empty($var)
    {
        if(is_null($var)) {
            return true ;
        }
        if(is_string($var)) {
            return strlen($var) == 0 ;
        }
        if(is_array($var) || is_iterable($var)) {
            return count($var) == 0 ;
        }

        throw new \InvalidArgumentException("parameter must be a string or an array or an iterable") ;
    }
}

if(!function_exists("getShopLogo"))
{
    function getShopLogo(array $shopInfo, string $default){
        return !array_key_exists("logo", $shopInfo) || is_empty($shopInfo["logo"]) ? $default : $shopInfo["logo"] ;
    }
}

if(!function_exists("getShopBanner"))
{
    function getShopBanner(array $shopInfo, string $default) {
        return !array_key_exists("banner", $shopInfo ) || is_empty($shopInfo["banner"]) ? $default : $shopInfo["banner"] ;
    }
}

if(!function_exists("getShopDescription"))
{
    function getShopDescription(string $shopName) {
        return "Explorez les produits disponibles sur {$shopName}." ;
    }
}

if(!function_exists("getShopDescription_bis"))
{
    function getShopDescription_bis(array $shopInfo, string $default = "") {
        return !array_key_exists("description", $shopInfo ) || is_empty($shopInfo["description"]) ? $default : $shopInfo["description"] ;
    }
}

if(!function_exists("getShopName"))
{
    function getShopName(array $shopInfo, string $default = "") {
        return (!array_key_exists("name", $shopInfo ) || is_empty($shopInfo["name"])) ? $default : $shopInfo["name"] ;
    }
}

if(!function_exists("getShopFacebookLink"))
{
    function getShopFacebookLink(array $shopInfo) {
        return !array_key_exists("facebookLink", $shopInfo ) || is_empty($shopInfo["facebookLink"]) ? '' : $shopInfo["facebookLink"] ;
    }
}

// Todo : test
if(!function_exists("getShopTwitterLink"))
{
    function getShopTwitterLink(array $shopInfo) {
        return !array_key_exists("twitterLink", $shopInfo ) || is_empty($shopInfo["twitterLink"]) ? '' : $shopInfo["twitterLink"] ;
    }
}

if(!function_exists("getShopInstagramLink"))
{
    function getShopInstagramLink(array $shopInfo) {
        return !array_key_exists("instagramLink", $shopInfo ) || is_empty($shopInfo["instagramLink"]) ? '' : $shopInfo["instagramLink"] ;
    }
}

// Todo : test
if(!function_exists("is_facebook_link"))
{
    function is_facebook_link($link) : bool
    {
        if (is_null($link) || strcmp($link, "" ) == 0 || strcmp($link, "#" == 0)) {
            return true ;
        }
        return Str::startsWith($link, ["https://www.facebook", "https://m.facebook", "https://web.facebook", "https://facebook"]) ;
    }
}

if(!function_exists("is_instagram_link"))
{
    function is_instagram_link($link) : bool
    {
        if (is_null($link) || strcmp($link, "" ) == 0 || strcmp($link, "#" == 0)) {
            return true ;
        }
        return Str::startsWith($link, ["https://www.instagram", "https://m.instagram", "https://web.instagram", "https://instagram"]) ;
    }
}

if(!function_exists("is_twitter_link"))
{
    function is_twitter_link($link) : bool
    {
        if (is_null($link) || strcmp($link, "" ) == 0 || strcmp($link, "#" == 0)) {
            return true ;
        }
        return is_empty($link) || Str::startsWith($link, ["https://m.twitter", "https://www.twitter", "https://twitter"]) ;
    }
}