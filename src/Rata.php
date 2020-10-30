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
 * @desc        To get remote content.
 */

namespace josecarlosphp\internet;

class Rata
{
    protected $_base = '';
    protected $_method = 'http';
    private $_debug = false;
    const METHODS = 'http,ftp,file';
    private $_errores = array();
    private $_tempdir = '';
    private $_curl = null;
    private $_con = null;
    /**
	 * Constructor
	 *
	 * @param string $base
	 */
	public function __construct($base)
    {
        $this->SetBase($base);
        $this->_tempdir = sys_get_temp_dir();
    }
    /**
	 * Establece la base
	 *
	 * @param string $str
	 */
	public function SetBase($str)
    {
        $this->_base = $str;
    }
    /**
	 * Obtiene la base actual
	 *
	 * @return string
	 */
	public function GetBase()
    {
        return $this->_base;
    }
    /**
	 * Establece el método
	 *
	 * @param string $str
	 * @return bool
	 */
	public function SetMethod($str)
    {
        $str = strtolower($str);
        if(self::ValidMethod($str))
        {
            $this->_method = $str;
            return true;
        }
        return false;
    }
    /**
	 * Obtiene el método actual
	 *
	 * @return string
	 */
	public function GetMethod()
    {
        return $this->_method;
    }
    /**
	 * Comprueba si una cadena se corresponde con un método
	 *
	 * @param string $str
	 * @return bool
	 */
	public static function ValidMethod($str)
    {
        return in_array($str, explode(',', self::METHODS));
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
	 * Establece el directorio de archivos temporales
	 *
	 * @param string $str
	 */
	public function SetTempDir($str)
    {
        if(is_dir($str))
        {
            $this->_tempdir = $str;
            return true;
        }
        $this->_errores[] = 'El directorio no existe';
        return false;
    }
    /**
	 * Obtiene el directorio de archivos temporales
	 *
	 * @return string
	 */
	public function GetTempDir()
    {
        return $this->_tempdir;
    }
	/**
	 * Obtiene la propiedad privada _curl
	 *
	 * @return resource
	 */
	public function GetCurl()
	{
		return $this->_curl;
	}
    /**
	 * Guarda el resultado de GetContents en un archivo
	 *
	 * @param string $filepath
	 * @param string $sub
	 * @param array $params
	 * @param bool $post
	 * @param string $func
	 * @return bool
	 */
	public function GetFile($filepath, $sub, $params=array(), $post=false, $func='', $opts=array())
    {
		if(!isset($opts[CURLOPT_FOLLOWLOCATION]))
		{
			$opts[CURLOPT_FOLLOWLOCATION] = true;
		}

		/*if(isset($params[0]) && is_array($params[0]))
        {
            //Múltiple (fusionar archivos)
            $contents = '';
            $aux = true;
            for($c=0,$size=sizeof($params); $c<$size; $c++)
            {
                if($contents && $c > 0 && $aux)
                {
                    $contents .= "\r\n";
                }

                $aux = trim($this->GetContents($sub, $params[$c], $post, $opts, $func));
                if($aux)
                {
                    $contents .= $aux;
                }
            }
        }
        else
        {*/
            //Sólo uno (normal)
			$contents = $this->GetContents($sub, $params, $post, $opts, $func);
		/*}*/

        if($contents !== false)
        {
            return $this->WriteFile($filepath, $contents);
        }

        return false;
    }
    /**
     * Graba un archivo con el contenido especificado
     *
     * @param string $contents
     * @param string $filepath
     * @return bool
     */
    public function WriteFile($filepath, $contents)
    {
        return file_put_contents($filepath, $contents) !== false;
    }
    /**
	 * Obtiene el contenido, o false si va mal
	 *
	 * @param string $sub
	 * @param array $params
	 * @param bool $post
	 * @param array $opts
     * @param string $func
     * @param bool $close
	 * @return string
	 */
	public function GetContents($sub, $params=array(), $post=false, $opts=array(), $func='', $close=true)
    {
		//$this->MsgDbg(var_export($params, true));

        $resultado = false;
        switch($this->_method)
        {
            case 'http':
                $this->Open();

				foreach($opts as $key=>$value)
				{
					if(!$this->_curl->SetOpt($key, $value))
                    {
                        $this->MsgDbg($this->_curl->Error());
                    }
				}

                $url = $sub ? ponerBarra($this->_base) . $sub : $this->_base;
                if(!empty($params) && !$post)
                {
                    $url .= '?'.array_ToQueryString($params);
                }

                $this->MsgDbg('<pre>cURL ' . var_export(array(
                    'url' => $url,
                    'params' => $params,
                    'post' => $post,
                    'opts' => var_export($opts, true),
                ), true) . '</pre>');

                $resultado = $this->_curl->Go($url, $post ? $params : array());
                $info = $this->_curl->GetInfo();

                $this->MsgDbg('<pre>cURL info = '.var_export($info, true).'</pre>');

                if($resultado === false)
                {
                    $this->_errores[] = $this->_curl->Error();
                    $this->MsgDbg('ERROR cURL '.$this->_curl->ErrNo().': '.$this->_curl->Error());
                }
                elseif(isset($info['http_code']))
                {
                    switch((int)$info['http_code'])
                    {
                        case 401:
                        case 403:
                        case 404:
                            //sigue
                        case 500:
                        case 502:
                        case 503:
                            $this->_errores[] = 'ERROR '.$info['http_code'];
                            $this->MsgDbg('ERROR '.$info['http_code']);
                            $resultado = false;
                            break;
                    }
                }
				
                if($close)
                {
                    $this->Close();
                }

                break;
            case 'ftp':
                $this->Open();
                if($this->_con)
                {
					if(ftp_login($this->_con, $params['user'], $params['pass']))
                    {
						if(isset($params['ftp_pasv']))
						{
							ftp_pasv($this->_con, $params['ftp_pasv'] ? true : false);
						}

                        $filepath = tempnam($this->_tempdir, 'Rata');
                        switch($sub)
                        {
                            case '[LAST_BY_DDMMYY]':
								$resultado = '';
								$files = ftp_nlist($this->_con, '.');
								if(is_array($files))
								{
									if(empty($files))
									{
										$this->MsgDbg('ftp_nlist devolvió 0 elementos');
									}
									else
									{
										if(!isset($params['multiple']))
										{
											$params['multiple'][] = $params;
										}

										$primero = true;
										foreach($params['multiple'] as $item)
										{
											$sufijo = isset($item['sufijo']) ? $item['sufijo'] : '';
											$pattern = isset($item['pattern']) ? $item['pattern'] : '';

											$max = '';
											$lastfile = '';

											foreach($files as $c=>$file)
											{
												if(!$pattern || preg_match($pattern, $file))
												{
													//$this->MsgDbg($pattern.' '.$file.' SI');

													$pos = $sufijo ? strrpos($file, $sufijo) : strlen($file);
													$file = substr($file, 0, $pos);
													$file = substr($file, $pos - 6); //6 es la longitud de DDMMYY
													$aux = substr($file, 4, 2) . substr($file, 2, 2) . substr($file, 0, 2);
													if($aux > $max)
													{
														$max = $aux;
														$lastfile = $files[$c];
													}
												}
											}

											$this->MsgDbg($lastfile);

											$aux = ftp_get($this->_con, $filepath, $lastfile, FTP_BINARY, 0) ? file_get_contents($filepath) : false;

											if(!$primero)
											{
												$aux = mb_substr($aux, mb_strpos($aux, "\n"));
											}

											$resultado .= $aux;

											$primero = false;
										}
									}
								}
								else
								{
									$this->MsgDbg('ftp_nlist devolvió '.var_export($files, true));
								}
                                break;
                            case '[LAST_BY_NAME]':
								$prefijo = isset($params['prefijo']) ? $params['prefijo'] : '';
								$ext = isset($params['ext']) ? $params['ext'] : '';
                                $len = strlen($prefijo);
								$files = ftp_nlist($this->_con, '.');
                                $aux = array();
								foreach($files as $c=>$file)
                                {
                                    if((!$prefijo || substr($file, 0, $len) == $prefijo) && (!$ext || getExtension($file) == $ext))
									{
										$aux[] = $file;
									}
                                }
								usort($aux, 'strcasecmp');
								$lastfile = array_pop($aux);
								$this->MsgDbg($lastfile);
								$resultado = ftp_get($this->_con, $filepath, $lastfile, FTP_BINARY, 0) ? file_get_contents($filepath) : false;
                                if(isset($params['limpiar']) && $params['limpar'])
								{
									foreach($aux as $file)
									{
										ftp_delete($this->_con, $file);
									}
								}
                                break;
                            case '[LAST_BY_DATE]':
								$prefijo = isset($params['prefijo']) ? $params['prefijo'] : '';
								$ext = isset($params['ext']) ? $params['ext'] : '';
								$files = ftp_nlist($this->_con, '.');
								$lastfile = '';
								$lastdate = 0;
								$aux = array();
								foreach($files as $c=>$file)
                                {
                                    if((!$prefijo || substr($file, 0, $len) == $prefijo) && (!$ext || getExtension($file) == $ext))
									{
										$aux[] = $file;

										if(ftp_mdtm($this->_con, $file) > $lastdate)
										{
											$lastfile = $file;
										}
									}
                                }
								$this->MsgDbg($lastfile);
								$resultado = ftp_get($this->_con, $filepath, $lastfile, FTP_BINARY, 0) ? file_get_contents($filepath) : false;
                                if(isset($params['limpiar']) && $params['limpar'])
								{
									foreach($aux as $file)
									{
										ftp_delete($this->_con, $file);
									}
								}
								break;
                            default:
                                $this->MsgDbg($sub);
                                $resultado = ftp_get($this->_con, $filepath, $sub, FTP_BINARY, 0) ? file_get_contents($filepath) : false;
                                break;
                        }
                        @unlink($filepath);
                    }
                    else
                    {
                        $this->_errores[] = 'No se pudo autenticar';
                        $this->MsgDbg('ERROR FTP: No se pudo autenticar');
                    }
                    if($close)
                    {
                        $this->Close();
                    }
                }
                else
                {
                    $this->_errores[] = 'No se pudo conectar';
                    $this->MsgDbg('ERROR FTP: No se pudo conectar');
                }
                break;
			case 'file':
				$filepath = ponerBarra($this->_base).$sub;
				if(is_file($filepath) || mb_substr(trim($filepath), 0, 4) == 'http')
				{
					if(is_readable($filepath) || mb_substr(trim($filepath), 0, 4) == 'http')
					{
						$resultado = file_get_contents($filepath);
					}
					else
					{
						$this->_errores[] = 'No se pudo leer el archivo';
						$this->MsgDbg('ERROR FILE: No se pudo leer el archivo');
					}
				}
				else
				{
					$this->_errores[] = 'No existe el archivo';
					$this->MsgDbg('ERROR FILE: No existe el archivo');
				}
				break;
        }

        if($resultado !== false)
        {
            $resultado = self::ApplyFunc($resultado, $func);
        }

        return $resultado;
    }
    /**
	 * Aplica una función o funciones a una variable
	 *
	 * @param mixed $var
	 * @param string $func
	 * @return mixed
	 */
	public static function ApplyFunc($var, $func)
    {
        if(is_array($func))
        {
            foreach($func as $f)
            {
                $var = self::ApplyFunc($var, $f);
            }
        }
        elseif(mb_strpos($func, '$var') !== false)
        {
            $var = eval("return {$func};");
        }
        elseif($func && function_exists($func))
        {
            $var = $func($var);
        }

        return $var;
    }

	public function Ratear($arr)
	{
		if(!empty($arr))
		{
			$contents = '';
			foreach($arr as $aux)
			{
                if(!empty($aux['params']))
                {
                    foreach($aux['params'] as $key=>$value)
                    {
                        if(is_array($value) && isset($value[0], $value[1]))
                        {
                            $aux['params'][$key] = cogerTrozo($contents, $value[0], $value[1]);
                        }
                    }
                }

				$opts = array();
				if(!empty($aux['referer']))
				{
					$opts[CURLOPT_REFERER] = $aux['referer'];
				}
				$opts[CURLOPT_FOLLOWLOCATION] = !isset($aux['followlocation']) || $aux['followlocation'];
				if(!empty($aux['useragent']))
				{
					$opts[CURLOPT_USERAGENT] = $aux['useragent'];
				}

				$contents = $this->GetContents(
                    $aux['url'],
                    isset($aux['params']) ? $aux['params'] : array(),
                    !empty($aux['post']),
                    $opts,
                    '',
                    false
                    );

				//TODO: eval($aux['ok'])
			}

			return $contents;
		}

		return null;
	}
    /**
     * Inicia la conexión según el método
     *
     * @param bool $reset
     */
    protected function Open($reset = false)
    {
        switch($this->_method)
        {
            case 'http':
                if(empty($this->_curl) || $reset)
                {
                    $this->MsgDbg('Open cURL');
                    $this->_curl = new Curl(true, $this->_tempdir);
					$this->_curl->SetDebug($this->_debug);
                    //$this->_curl->AutoReferer(true);
                }
                break;
            case 'ftp':
                if(empty($this->_con) || $reset)
                {
                    $this->MsgDbg('Open FTP');
                    $this->_con = ftp_connect($this->_base);
                }
                break;
        }
    }
    /**
     * Cierra la conexión según el método
     */
    protected function Close()
    {
        switch($this->_method)
        {
            case 'http':
                $this->MsgDbg('Close cURL');
                $this->_curl->Close();
                break;
            case 'ftp':
                $this->MsgDbg('Close FTP');
                ftp_quit($this->_con);
                break;
        }
    }
    /**
	 * Muestra un mensaje si modo debug está activado
	 *
	 * @param string $str
	 */
	protected function MsgDbg($str)
    {
        if($this->_debug)
        {
            echo "La rata dice: {$str}<br />\n";
            flush();
        }
    }
    /**
	 * Obtiene el texto del último error
	 *
	 * @return string
	 */
	public function Error()
    {
        $size = sizeof($this->_errores);
        return $size > 0 ? $this->_errores[$size - 1] : '';
    }
}
