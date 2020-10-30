<?php
/**
 * This file is part of josecarlosphp/reader - PHP classes to read from different sources.
 *
 * josecarlosphp/internet is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @see         https://github.com/josecarlosphp/internet
 * @copyright   2012-2020 José Carlos Cruz Parra
 * @license     https://www.gnu.org/licenses/gpl.txt GPL version 3
 * @desc        To work with cUrl.
 */

namespace josecarlosphp\internet;

class Curl
{
	private static $_isAvailable = false;
    private $_ch;
    private $_errores = array();
    private $_debug = false;
    private $_cookieDir = '';
    private $_cookieFile = 'cookie.txt';
    private $_cookiePath = '';
    private $_caInfo = false;
    private $_deleteCookie = false;
	private $_fileAsGetContents = false;
	private $_opts = array();
	private $_isAbleToFollowLocation = true;
	private $_redirections = 0;
	private $_autoRetryCorrectingUrl = true;
	private $_autoReferer = false;
	private $_autoFollowLocation = true;
    private $_autoSSL = true;
	private $_history = array();
    private $_header = array();

    private static $lastCurlError = '';
    private static $lastCurlInfo = array();
	/**
	 * Constructor
	 *
	 * @param bool $init
	 */
    public function __construct($init=true, $cookieDir='temp/', $caInfo='ca-bundle.crt')
    {
		$this->SetCookieDir($cookieDir);
        $this->SetCaInfo($caInfo);

		if($init)
        {
            $this->Init();
        }

		//FOLLOWLOCATION no se puede usar si en el alojamiento hay establecido un open_basedir o safe_mode está activado
		$this->_isAbleToFollowLocation = (ini_get('open_basedir') == '' && ini_get('safe_mode') == 'Off');
    }
	/**
	 * Destructor
	 *
	 */
    public function __destruct()
    {
        $this->Close();
    }
	/**
	 * Dice si la extensión curl está disponible o no
	 *
	 * @return boolean
	 */
	public static function stcIsAvailable()
	{
		self::$_isAvailable = self::$_isAvailable || function_exists('curl_init');

		return self::$_isAvailable;
	}
	/**
	 * Dice si la extensión curl está disponible o no,
	 * y si es que no lo registra como error
	 *
	 * @return boolean
	 */
	private function IsAvailable()
	{
		if(self::stcIsAvailable())
		{
			return true;
		}
		else
		{
			$this->AddError('Curl is not available');
		}

		return false;
	}
	/**
	 * Inicializa la sesión cURL con las opciones por defecto
	 *
	 * @return boolean
	 */
    public function Init()
    {
		if($this->IsAvailable())
		{
			$this->_ch = curl_init();
			if($this->_ch !== false)
			{
				$ok = true;

				$this->_opts = array();

				$ok &= $this->SetOpt(CURLOPT_RETURNTRANSFER, true);
				$ok &= $this->SetOpt(CURLOPT_HEADER, false);
				$ok &= $this->SetOpt(CURLOPT_AUTOREFERER, true);

				if(is_dir($this->_cookieDir))
				{
					$ok &= $this->SetOpt(CURLOPT_COOKIEJAR, $this->_cookiePath);
					$ok &= $this->SetOpt(CURLOPT_COOKIEFILE, $this->_cookiePath);
				}

				return $ok;
			}
		}

		return false;
    }
	/**
	 * Establece modo depuración activado/desactivado
	 *
	 * @param bool $val
	 */
	public function SetDebug($val = true)
    {
        $this->_debug = $val ? true : false;
    }
	/**
	 * Establece el directorio para cookies
	 *
	 * @param string $str
	 */
    public function SetCookieDir($str)
    {
        $len = mb_strlen($str);
		if($len > 0 && mb_substr($str, $len - 1) != '/')
        {
            $str .= '/';
        }

		$this->_cookieDir = is_dir(getcwd().'/'.$str) ? getcwd().'/'.$str : $str;
        $this->_cookiePath = $this->_cookieDir.$this->_cookieFile;

		if(is_dir($this->_cookieDir) && is_resource($this->_ch))
		{
			$this->SetOpt(CURLOPT_COOKIEJAR, $this->_cookiePath);
			$this->SetOpt(CURLOPT_COOKIEFILE, $this->_cookiePath);
		}
    }
	/**
	 * Establece el nombre de archivo para cookie
	 *
-	 * @param string $str
	 */
    public function SetCookieFile($str)
    {
        if($this->_deleteCookie && is_file($this->_cookiePath))
        {
            unlink($this->_cookiePath);
        }

        $this->_cookieFile = $str;
        $this->_cookiePath = $this->_cookieDir.$this->_cookieFile;

		if(is_dir($this->_cookieDir) && is_resource($this->_ch))
		{
			$this->SetOpt(CURLOPT_COOKIEJAR, $this->_cookiePath);
			$this->SetOpt(CURLOPT_COOKIEFILE, $this->_cookiePath);
		}
    }
	/**
	 * Establece la ruta del archivo para información del certificado.
     * False para pasar de errores SSL.
	 *
	 * @param mixed $str
	 */
    public function SetCaInfo($str)
    {
        $this->_caInfo = ($str === false || !is_file(getcwd().'/'.$str)) ? $str : getcwd().'/'.$str;
    }
	/**
	 * Establece una opción cURL
	 *
	 * @param string $opt
	 * @param mixed $val
	 * @return bool
	 */
    public function SetOpt($opt, $val)
    {
		if($this->IsAvailable())
		{
			switch($opt)
			{
				case CURLOPT_FOLLOWLOCATION:
					if(!$this->_isAbleToFollowLocation)
					{
						$this->_opts[$opt] = $val;

						return true; //!!!
					}
					break;
                case CURLOPT_CAINFO:
                    if(!$val)
                    {
                        $this->_opts[$opt] = $val;
                        $this->_caInfo = $val;

                        return true; //!!!
                    }
                    break;
			}

			if(curl_setopt($this->_ch, $opt, $val))
			{
                $this->_opts[$opt] = $val;

                switch($opt)
                {
                    case CURLOPT_CAINFO:
                        if($this->_caInfo != $val)
                        {
                            $this->_caInfo = $val;
                        }
                        break;
                }

				return true;
			}

			$this->AddError("Can't assign {$opt} = {$val}");
		}

		return false;
    }
	/**
	 * Obtiene una opción cURL previamente establecida
	 *
	 * @param string $opt
	 * @return mixed
	 */
    public function GetOpt($opt)
    {
        return isset($this->_opts[$opt]) ? $this->_opts[$opt] : null;
    }
    /**
	 * Ejecuta la sesión cURL pero devolverá false si coincide alguna condición de baneo
	 *
	 * @param string $url
     * @param mixed $banStrs
     * @param mixed $banUrls
     * @param mixed $banWeights
	 * @param mixed $post
	 * @return mixed
	 */
    public function GoBan($url, $banStrs=array(), $banUrls=array(), $banWeights=array(), $post=array())
    {
        if(($response = $this->Go($url, $post)) !== false)
        {
            if(!empty($banStrs))
            {
                $last = $this->GetLastUrl();
                if(is_array($banStrs))
                {
                    foreach($banStrs as $str)
                    {
                        if(mb_strpos($last, $str) !== false)
                        {
                            $this->AddError('Banned string');
                            return false;
                        }
                    }
                }
                elseif(mb_strpos($last, $banStrs) !== false)
                {
                    $this->AddError('Banned string');
                    return false;
                }
            }

            if(!empty($banUrls))
            {
                $last = $this->GetLastUrl();
                if(is_array($banUrls))
                {
                    foreach($banUrls as $url)
                    {
                        if($url == $last)
                        {
                            $this->AddError('Banned URL');
                            return false;
                        }
                    }
                }
                elseif($banUrls == $last)
                {
                    $this->AddError('Banned URL');
                    return false;
                }
            }

            if(!empty($banWeights))
            {
                $rWeight = mb_strlen($response, '8bit');
                if(is_array($banWeights))
                {
                    foreach($banWeights as $weight)
                    {
                        if($weight == $rWeight)
                        {
                            $this->AddError('Banned weight');
                            return false;
                        }
                    }
                }
                elseif($banWeights == $rWeight)
                {
                    $this->AddError('Banned weight');
                    return false;
                }
            }
        }

        return $response;
    }
	/**
	 * Ejecuta la sesión cURL
	 *
	 * @param string $url
	 * @param mixed $post
	 * @return mixed
	 */
    public function Go($url, $post=array())
    {
		$this->_history[] = $url;
        $this->_header = array();

		if($this->_autoReferer)
		{
			$len = sizeof($this->_history) - 1; //Menos uno porque acabamos de añadir la url que vamos a visitar ahora
			if($len > 0)
			{
				$this->SetOpt(CURLOPT_REFERER, $this->_history[$len-1]);
			}
		}

		if($this->_autoFollowLocation && is_null($this->GetOpt(CURLOPT_FOLLOWLOCATION)))
		{
			$this->SetOpt(CURLOPT_FOLLOWLOCATION, true);
		}

		if($this->IsAvailable())
		{
			$aux_redirections = $this->_redirections;
			$this->_redirections = 0;

			if(!is_null($this->GetOpt(CURLOPT_MAXREDIRS)) && $aux_redirections > $this->GetOpt(CURLOPT_MAXREDIRS))
			{
				$this->AddError('Too many redirections');

				return false;
			}

			$this->SetOpt(CURLOPT_URL, $url);

			$protocol = substr($url, 0, strpos($url, ':'));

			if($protocol == 'file' && $this->_fileAsGetContents)
			{
				//Anteponer barra
				$url = substr($url, 7);
				$len = strlen($url);
				if($len == 0 || substr($url, 0, 1) != '/')
				{
					$url = '/'.$url;
				}

				if(is_file($url))
				{
					return file_get_contents($url);
				}
				else
				{
					$this->AddError('file_get_contents('.$url.'): failed to open stream: No such file or directory');

					return false;
				}
			}

			//SSL
            if($this->_autoSSL)
            {
                if($protocol == 'https' && $this->_caInfo && is_file($this->_caInfo))
                {
                    $this->SetOpt(CURLOPT_CAINFO, $this->_caInfo);
                    $this->SetOpt(CURLOPT_SSL_VERIFYPEER, true);
                    $this->SetOpt(CURLOPT_SSL_VERIFYHOST, 2);
                }
                else
                {
                    //Deshabilitar CURLOPT_CAINFO ?
                    $this->SetOpt(CURLOPT_SSL_VERIFYPEER, false);
                    $this->SetOpt(CURLOPT_SSL_VERIFYHOST, 0);
                }
            }

			//POST
			if(empty($post))
			{
				$this->SetOpt(CURLOPT_POST, false);
			}
			else
			{
				if(is_array($post))
				{
					$conFile = false;
					foreach($post as $val)
					{
						if(is_string($val) && mb_substr($val, 0, 1) == '@' && is_file(mb_substr($val, 1)))
						{
							$conFile = true;
							break;
						}
					}

					if(!$conFile)
					{
						//$post = array_ToQueryString($post);
						$post = http_build_query($post);
					}
				}

				$this->SetOpt(CURLOPT_POST, true);
				$this->SetOpt(CURLOPT_POSTFIELDS, $post);
			}

			$response = curl_exec($this->_ch);

			$curl_errno = curl_errno($this->_ch);
			if($curl_errno > 0)
			{
				if($curl_errno == 1)
				{
					$reintentar = false;

					if(!$this->_fileAsGetContents && substr($url, 0, 5) == 'file:')
					{
						//Intentar coger un archivo con file_get_contents

						$this->_fileAsGetContents = true;
						$reintentar = true;
					}
					elseif($this->_autoRetryCorrectingUrl)
					{
						//Correciones sobre la $url (podía estar mal puesta)

						if(substr($url, 0, 1) == ' ')
						{
							$url = substr($url, 1);
							$reintentar = true;
						}

						if(substr($url, 0, 6) == 'http:/')
						{
							if(substr($url, 6, 1) != '/')
							{
								$url = 'http://'.substr($url, 0, 6);
								$reintentar = true;
							}

							$trozos = explode('/', $url);
							for($c=2,$size=sizeof($trozos); $c<$size; $c++)
							{
								if(urldecode($trozos[$c]) == $trozos[$c])
								{
									$trozos[$c] = urlencode($trozos[$c]);
									$reintentar = true;
								}
							}
							$url = implode('/', $trozos);
						}
					}

					if($reintentar)
					{
						return $this->Go($url, $post);
					}
				}
				elseif($curl_errno == 35)
				{
					if(version_compare(phpversion(), '5.5', '>=') && $this->GetOpt(CURLOPT_SSLVERSION) != CURL_SSLVERSION_TLSv1)
					{
						$this->SetOpt(CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);

						return $this->Go($url, $post);
					}
					elseif($this->GetOpt(CURLOPT_SSLVERSION) < 3)
					{
						$this->SetOpt(CURLOPT_SSLVERSION, 3);

						return $this->Go($url, $post);
					}
				}

                $curl_error = curl_error($this->_ch);
                if((mb_stripos($curl_error, 'SSL certificate problem') !== false || mb_stripos($curl_error, 'Certificate issuer is not recognized')) && $this->_autoSSL)
                {
                    $this->_autoSSL = false;
                    $this->SetOpt(CURLOPT_SSL_VERIFYPEER, false);
                    $this->SetOpt(CURLOPT_SSL_VERIFYHOST, 0);

                    return $this->Go($url, $post);
                }

				$this->AddError(curl_error($this->_ch), $curl_errno);

				return false;
			}
			else
			{
				//Si queremos FOLLOWLOCATION pero el alojamiento no lo permite
				//entonces Curl no puede seguir la redirección y lo tenemos que hacer "a mano"
				if($this->GetOpt(CURLOPT_FOLLOWLOCATION) && !$this->_isAbleToFollowLocation)
				{
					$info = $this->GetInfo();

					if(!empty($info['redirect_url']))
					{
						$this->_redirections = $aux_redirections + 1;
						return $this->Go($info['redirect_url']);
					}
				}
			}

            if($this->GetOpt(CURLOPT_HEADER))
			{
				$len = $this->GetInfo(CURLINFO_HEADER_SIZE);
				$this->_header = $this->Header2Array(mb_substr($response, 0, $len));
				$response = mb_substr($response, $len);
			}

			return $response;
		}

		return false;
    }

    private function Header2Array($str)
	{
		$arr = array();
		$lines = explode("\n", $str);
		foreach($lines as $line)
		{
			if(($pos = mb_strpos($line, ':')) !== false)
			{
				$key = mb_substr($line, 0, $pos);
				if($key == 'Link')
				{
					$arr[$key] = array();
					$links = explode(',', mb_substr($line, $pos + 1));
					foreach($links as $link)
					{
						if(($pos1 = mb_strpos($link, 'rel="')) !== false && ($pos2 = mb_strpos($link, '<')) !== false)
						{
							$aux1 = mb_substr($link, $pos1 + 5);
							$aux1 = mb_substr($aux1, 0, mb_strpos($aux1, '"'));

							$aux2 = mb_substr($link, $pos2 + 1);
							$aux2 = mb_substr($aux2, 0, mb_strpos($aux2, '>'));

							$arr[$key][$aux1] = $aux2;
						}
					}
				}
				else
				{
					$arr[$key] = trim(mb_substr($line, $pos + 1));
				}
			}
		}

		return $arr;
	}

	public function GetHeader($key=null)
	{
		if(is_null($key))
		{
			return $this->_header;
		}
		elseif(isset($this->_header[$key]))
		{
			return $this->_header[$key];
		}

		return false;
	}
	/**
	 * Obtiene información
	 *
	 * @return mixed
	 */
    public function GetInfo($opt=0)
    {
		if($this->IsAvailable())
		{
			return $opt > 0 ? curl_getinfo($this->_ch, $opt) : curl_getinfo($this->_ch);
		}

		return false;
    }
	/**
	 * Obtiene el texto del último error
	 *
	 * @param bool $conNum
	 * @return string
	 */
    public function Error($conNum=false)
    {
        $size = sizeof($this->_errores);
        return $size > 0 ? ($conNum ? $this->_errores[$size - 1][0].' - '.$this->_errores[$size - 1][1] : $this->_errores[$size - 1][1]) : '';
    }
	/**
	 * Obtiene el código del último error
	 *
	 * @return string
	 */
    public function ErrNo()
    {
        $size = sizeof($this->_errores);
        return $size > 0 ? $this->_errores[$size - 1][0] : '';
    }
	/**
	 * Cierra la sesión cURL
	 *
	 */
    public function Close()
    {
        if(is_resource($this->_ch))
        {
            curl_close($this->_ch);
        }
    }
	/**
	 * Registra un error
	 *
	 * @param string $txt
	 * @param int $num
	 */
    protected function AddError($txt, $num=0)
    {
		if($this->_debug)
		{
			$txt .= "\n<br />curl_info = ".var_export($this->GetInfo(), true);
		}

        $this->_errores[] = array(0=>$num, 1=>$txt);
    }
	/**
	 * Obtiene/Establece la propiedad _fileAsGetContents
	 * que indica si el contenido se tiene que coger con file_get_contents en vez de con curl_exec
	 * cuando el protocolo es file.
	 *
	 * @param bool $val
	 * @return bool
	 */
	public function FileAsGetContents($val=null)
	{
		if(!is_null($val))
		{
			$this->_fileAsGetContents = ($val ? true : false);
		}

		return $this->_fileAsGetContents;
	}
	/**
	 * Obtiene/Establece la propiedad _autoRetryCorrectingUrl
	 *
	 * @param bool $val
	 * @return bool
	 */
	public function RetryCorrectingUrl($val=null)
	{
		if(!is_null($val))
		{
			$this->_autoRetryCorrectingUrl = ($val ? true : false);
		}

		return $this->_autoRetryCorrectingUrl;
	}
	/**
	 * Obtiene/Establece la propiedad _autoReferer
	 *
	 * @param bool $val
	 * @return bool
	 */
	public function AutoReferer($val=null)
	{
		if(!is_null($val))
		{
			$this->_autoReferer = ($val ? true : false);
		}

		return $this->_autoReferer;
	}
	/**
	 * Obtiene/Establece la propiedad _autoFollowLocation
	 *
	 * @param bool $val
	 * @return bool
	 */
	public function AutoFollowLocation($val=null)
	{
		if(!is_null($val))
		{
			$this->_autoFollowLocation = ($val ? true : false);
		}

		return $this->_autoFollowLocation;
	}
    /**
	 * Obtiene/Establece la propiedad _autoSSL
	 *
	 * @param bool $val
	 * @return bool
	 */
	public function AutoSSL($val=null)
	{
		if(!is_null($val))
		{
			$this->_autoSSL = ($val ? true : false);
		}

		return $this->_autoSSL;
	}

	public function GetHistory()
	{
		return $this->_history;
	}

	public function GetLastUrl()
	{
		$len = sizeof($this->_history);
		return $len > 0 ? $this->_history[$len-1] : null;
	}
    /**
	 * Lee una página con curl (forma fácil y rápida)
	 *
	 * @param string $str
	 * @param array $post
	 * @param string $f
	 * @param array $opts
     * @param string $cookieDir
	 * @return string
	 */
	public static function Curl($str, $post=array(), $f='utf8_encode_once', $opts=array(), $cookieDir='temp/', $caInfo='ca-bundle.crt')
    {
        $curl = new Curl(true, $cookieDir, $caInfo);

        foreach($opts as $key=>$val)
		{
			$curl->SetOpt($key, $val);
		}

        $aux = $curl->Go($str, $post);
        self::$lastCurlInfo = $curl->GetInfo();
        if($aux === false)
        {
            self::$lastCurlError = $curl->Error();
        }
		elseif($f)
		{
			$aux = $f($aux);
		}

        $curl->Close();

		return $aux;
    }
    /**
     * Obtiene el último error producido dentro del método estático Curl()
     *
     * @return string
     */
    public static function CurlError()
	{
		return self::$lastCurlError;
	}
    /**
     * Obtiene el último conjunto de información producido dentro del método estático Curl()
     *
     * @return array
     */
	public static function CurlInfo()
	{
		return self::$lastCurlInfo;
	}
}
