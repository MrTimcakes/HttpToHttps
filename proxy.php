<?php
include('httpful.phar');

define("PROXY_PREFIX", "http" . (isset($_SERVER["HTTPS"]) ? "s" : "") . "://" . $_SERVER["HTTP_HOST"] . "/");

$url = substr($_SERVER['REQUEST_URI'],1);
if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
    die('Not a valid URL');
}

$defaultHeaders = array("Host" => "","Connection" => "","Cache-Control" => "","Upgrade-Insecure-Requests" => "","User-Agent" => "","Accept" => "","DNT" => "","Accept-Encoding" => "","Accept-Language" => "");
$customHeaders = array_diff_key(getallheaders(), $defaultHeaders);

$response = \Httpful\Request::get($url)
  ->addHeaders($customHeaders)
  ->send();

if(strlen($response->meta_data["redirect_url"]) != 0){
    //die("Redirect:" . $response->meta_data["redirect_url"]);
    header('Location: ' . PROXY_PREFIX . $response->meta_data["redirect_url"]);
}

// echo "<pre>";
// print_r($response);
// echo "<pre";

//echo $response->raw_body;


$responseBody = $response->raw_body;


$contentType = "";
if (isset($response->headers["content-type"])) $contentType = $response->headers["content-type"];
//This is presumably a web page, so attempt to proxify the DOM.
if (stripos($contentType, "text/html") !== false) {
  //Attempt to normalize character encoding.
  $detectedEncoding = mb_detect_encoding($responseBody, "UTF-8, ISO-8859-1");
  if ($detectedEncoding) {
    $responseBody = mb_convert_encoding($responseBody, "HTML-ENTITIES", $detectedEncoding);
  }
  //Parse the DOM.
  $doc = new DomDocument();
  @$doc->loadHTML($responseBody);
  $xpath = new DOMXPath($doc);
  //Rewrite forms so that their actions point back to the proxy.
  foreach($xpath->query("//form") as $form) {
    $method = $form->getAttribute("method");
    $action = $form->getAttribute("action");
    //If the form doesn't have an action, the action is the page itself.
    //Otherwise, change an existing action to an absolute version.
    $action = empty($action) ? $url : rel2abs($action, $url);
    //Rewrite the form action to point back at the proxy.
    $form->setAttribute("action", rtrim(PROXY_PREFIX, "?"));
    //Add a hidden form field that the proxy can later use to retreive the original form action.
    $actionInput = $doc->createDocumentFragment();
    $actionInput->appendXML('<input type="hidden" name="miniProxyFormAction" value="' . $action . '" />');
    $form->appendChild($actionInput);
  }
  //Proxify <meta> tags with an 'http-equiv="refresh"' attribute.
  foreach ($xpath->query("//meta[@http-equiv]") as $element) {
    if (strcasecmp($element->getAttribute("http-equiv"), "refresh") === 0) {
      $content = $element->getAttribute("content");
      if (!empty($content)) {
        $splitContent = preg_split("/=/", $content);
        if (isset($splitContent[1])) {
          $element->setAttribute("content", $splitContent[0] . "=" . PROXY_PREFIX . rel2abs($splitContent[1], $url));
        }
      }
    }
  }
  //Profixy <style> tags.
  foreach($xpath->query("//style") as $style) {
    $style->nodeValue = proxifyCSS($style->nodeValue, $url);
  }
  //Proxify tags with a "style" attribute.
  foreach ($xpath->query("//*[@style]") as $element) {
    $element->setAttribute("style", proxifyCSS($element->getAttribute("style"), $url));
  }
  //Proxify "srcset" attributes in <img> tags.
  foreach ($xpath->query("//img[@srcset]") as $element) {
    $element->setAttribute("srcset", proxifySrcset($element->getAttribute("srcset"), $url));
  }
  //Proxify any of these attributes appearing in any tag.
  $proxifyAttributes = array("href", "src");
  foreach($proxifyAttributes as $attrName) {
    foreach($xpath->query("//*[@" . $attrName . "]") as $element) { //For every element with the given attribute...
      $attrContent = $element->getAttribute($attrName);
      if ($attrName == "href" && preg_match("/^(about|javascript|magnet|mailto):/i", $attrContent)) continue;
      $attrContent = rel2abs($attrContent, $url);
      $attrContent = PROXY_PREFIX . $attrContent;
      $element->setAttribute($attrName, $attrContent);
    }
  }

  echo "<!-- Proxified page constructed by httptohttps.xyz -->\n" . $doc->saveHTML();
} else if (stripos($contentType, "text/css") !== false) { //This is CSS, so proxify url() references.
  echo proxifyCSS($responseBody, $url);
} else { //This isn't a web page or CSS, so serve unmodified through the proxy with the correct headers (images, JavaScript, etc.)
  header("Content-Length: " . strlen($responseBody));
  echo $responseBody;
}






















//Converts relative URLs to absolute ones, given a base URL.
//Modified version of code found at http://nashruddin.com/PHP_Script_for_Converting_Relative_to_Absolute_URL
function rel2abs($rel, $base) {
  if (empty($rel)) $rel = ".";
  if (parse_url($rel, PHP_URL_SCHEME) != "" || strpos($rel, "//") === 0) return $rel; //Return if already an absolute URL
  if ($rel[0] == "#" || $rel[0] == "?") return $base.$rel; //Queries and anchors
  extract(parse_url($base)); //Parse base URL and convert to local variables: $scheme, $host, $path
  $path = isset($path) ? preg_replace("#/[^/]*$#", "", $path) : "/"; //Remove non-directory element from path
  if ($rel[0] == "/") $path = ""; //Destroy path if relative url points to root
  $port = isset($port) && $port != 80 ? ":" . $port : "";
  $auth = "";
  if (isset($user)) {
    $auth = $user;
    if (isset($pass)) {
      $auth .= ":" . $pass;
    }
    $auth .= "@";
  }
  $abs = "$auth$host$port$path/$rel"; //Dirty absolute URL
  for ($n = 1; $n > 0; $abs = preg_replace(array("#(/\.?/)#", "#/(?!\.\.)[^/]+/\.\./#"), "/", $abs, -1, $n)) {} //Replace '//' or '/./' or '/foo/../' with '/'
  return $scheme . "://" . $abs; //Absolute URL is ready.
}
//Proxify contents of url() references in blocks of CSS text.
function proxifyCSS($css, $baseURL) {
  return preg_replace_callback(
    '/url\((.*?)\)/i',
    function($matches) use ($baseURL) {
        $url = $matches[1];
        //Remove any surrounding single or double quotes from the URL so it can be passed to rel2abs - the quotes are optional in CSS
        //Assume that if there is a leading quote then there should be a trailing quote, so just use trim() to remove them
        if (strpos($url, "'") === 0) {
          $url = trim($url, "'");
        }
        if (strpos($url, "\"") === 0) {
          $url = trim($url, "\"");
        }
        if (stripos($url, "data:") === 0) return "url(" . $url . ")"; //The URL isn't an HTTP URL but is actual binary data. Don't proxify it.
        return "url(" . PROXY_PREFIX . rel2abs($url, $baseURL) . ")";
    },
    $css);
}
//Proxify "srcset" attributes (normally associated with <img> tags.)
function proxifySrcset($srcset, $baseURL) {
  $sources = array_map("trim", explode(",", $srcset)); //Split all contents by comma and trim each value
  $proxifiedSources = array_map(function($source) use ($baseURL) {
    $components = array_map("trim", str_split($source, strrpos($source, " "))); //Split by last space and trim
    $components[0] = PROXY_PREFIX . rel2abs(ltrim($components[0], "/"), $baseURL); //First component of the split source string should be an image URL; proxify it
    return implode($components, " "); //Recombine the components into a single source
  }, $sources);
  $proxifiedSrcset = implode(", ", $proxifiedSources); //Recombine the sources into a single "srcset"
  return $proxifiedSrcset;
}
