<?php

namespace Agility\Server;

use Agility\Configuration;
use Agility\Http\Sessions\Session;
use Agility\Http\Upload;
use ArrayUtils\Arrays;
use AttributeHelper\Accessor;

	final class Request implements \JsonSerializable {

		use Accessor;

		protected $request;

		protected $host;
		protected $port;
		protected $headers;
		protected $ip;
		protected $method;
		protected $params;
		protected $uri;

		protected $getParams;
		protected $postParams;
		protected $fileParams;
		protected $cookie;

		protected $format;

		function __construct() {

			$this->request = $_SERVER;
			$this->headers = new Arrays(getallheaders());
			$this->params = new Arrays;
			$this->cookie = new Arrays;
			$this->fileParams = new Arrays;
			$this->compileParameters();

			$this->methodsAsProperties("delete", "get", "options", "patch", "post", "put");
			$this->readonly("host", "port", "headers", "ip", "method", "params", "uri", "getParams", "postParams", "fileParams", "cookie", "format");

		}

		function __debugInfo() {
			return ["ip" => $this->ip, "method" => $this->method, "params" => $this->params, "uri" => $this->uri, "cookie" => $this->cookie];
		}

		function addParameter($name, $value) {
			$this->params[$name] = $value;
		}

		function compileAcceptHeader($defaultAccept = "text/html") {
			$this->format = new RequestFormat($this->request["HTTP_ACCEPT"] ?? "", $defaultAccept);
		}

		protected function compileParameters() {

			$this->host = $this->request["HTTP_HOST"];
			$this->ip = $this->request["REMOTE_ADDR"];
			$this->method = strtolower($this->request["REQUEST_METHOD"]);
			$this->uri = $this->request["REQUEST_URI"];
			if (strpos($this->uri, "?") !== false) {
				$this->uri = explode("?", $this->uri)[0];
			}

			$this->getParams = new Arrays($_GET);
			$this->postParams = new Arrays($_POST);
			if ($this->postParams->exists("_method_") && in_array(strtolower($this->postParams["_method_"]), ["put", "patch"])) {
				$this->method = strtolower($this->postParams["_method_"]);
			}

			if (!empty($_COOKIE)) {

				foreach ($_COOKIE as $name => $value) {
					$this->cookie[$name] = $value;
				}

			}

			if ($this->method != "get") {

				if ($this->postParams->empty) {
					$this->postParams = new Arrays(json_decode(file_get_contents("php://input"), true));
				}

			}

			if (!empty($_FILES)) {

				foreach ($_FILES as $param => $value) {

					if (is_array($value["tmp_name"])) {

						$this->fileParams[$param] = new Arrays;

						foreach ($value["tmp_name"] as $key => $file) {
							$this->fileParams[$param][$key] = Upload::prepare($key, Upload::constructFileArray($key, $value));
						}

					}
					else {
						$this->fileParams[$param] = Upload::prepare($param, $value);
					}

				}

			}

		}

		function identifySession() {

			if (Configuration::sessionStore()->sessionSource == "cookie") {

				if ($this->cookie->exists(Configuration::sessionStore()->cookieName)) {
					return Session::buildFromCookie($this->cookie[Configuration::sessionStore()->cookieName]);
				}

			}
			else if (is_array(Configuration::sessionStore()->sessionSource) && isset(Configuration::sessionStore()->sessionSource["header"])) {

				$header = Configuration::sessionStore()->sessionSource["header"];
				if ($this->headers->exists($header)) {
					return Session::buildFromHeader($this->headers[$header]);
				}

			}

			return new Session;

		}

		function delete() {
			return $this->method == "delete";
		}

		function get() {
			return $this->method == "get";
		}

		function options() {
			return $this->method == "options";
		}

		function patch() {
			return $this->method == "patch";
		}

		function post() {
			return $this->method == "post";
		}

		function put() {
			return $this->method == "put";
		}

		function jsonSerialize() {

			return [
				"host" => $this->host,
				"port" => $this->port,
				"ip" => $this->ip,
				"uri" => $this->uri,
				"params" => $this->params,
				"method" => $this->method,
				"get" => $this->getParams,
				"post" => $this->postParams,
				"headers" => $this->headers,
				"cookies" => $this->cookie,
				"files" => $this->fileParams->keys
			];

		}

	}

?>