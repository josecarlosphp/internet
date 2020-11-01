<?php
/**
 * This file is part of josecarlosphp/internet - PHP classes to read from different sources.
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
 * @desc        To work with FTP.
 */

namespace josecarlosphp\internet;

class Ftp
{
    const CONNECTWAY_NORMAL = 1;
    const CONNECTWAY_SSL    = 2;

    private static $_isAvailable = false;
    private $_conn               = false;
    private $_errores            = array();
    private $_debug              = false;
    private $_timeout            = 60;
    private $_host;
    private $_port;
    private $_user;
    private $_pass;
    private $_way;

    /**
     * Constructor
     *
     * @param bool $init
     */
    public function __construct($host = '', $user = '', $pass = '', $way = self::CONNECTWAY_NORMAL, $port = 21, $timeout = 60)
    {
        $this->_host    = $host;
        $this->_port    = $port;
        $this->_user    = $user;
        $this->_pass    = $pass;
        $this->_way     = $way;
        $this->_timeout = $timeout;
    }

    /**
     * Destructor
     *
     */
    public function __destruct()
    {
        $this->Close();
    }

    private static function Prop($q, $val = null)
    {
        if (isset(self::$$q)) {
            if (!is_null($val)) {
                self::$$q = $val;
            }

            return self::$$q;
        }

        return null;
    }

    public function Timeout($val = null)
    {
        return self::Prop('_timeout', $val);
    }

    public function Host($val = null)
    {
        return self::Prop('_host', $val);
    }

    public function Port($val = null)
    {
        return self::Prop('_port', $val);
    }

    public function User($val = null)
    {
        return self::Prop('_user', $val);
    }

    public function Pass($val = null)
    {
        return self::Prop('_pass', $val);
    }

    public function Way($val = null)
    {
        return self::Prop('_way', $val);
    }

    /**
     * Dice si las funciones ftp están disponibles o no
     *
     * @return boolean
     */
    public static function stcIsAvailable()
    {
        self::$_isAvailable = self::$_isAvailable || function_exists('ftp_connect');

        return self::$_isAvailable;
    }

    /**
     * Dice si las funciones ftp están disponibles o no,
     * y si es que no lo registra como error
     *
     * @return boolean
     */
    private function IsAvailable()
    {
        if (self::stcIsAvailable()) {
            return true;
        } else {
            $this->AddError('FTP is not available');
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
     * Establece el modo pasivo (sí o no)
     *
     * @param boolean $val
     * @return boolean
     */
    public function SetPassiveMode($val)
    {
        if ($this->IsAvailable()) {
            return ftp_pasv($this->_conn, $val);
        }

        return false;
    }

    public function Pasv($val)
    {
        return $this->SetPassiveMode($val);
    }

    /**
     * Establece una opción FTP
     *
     * @param string $opt
     * @param mixed $val
     * @return bool
     */
    public function SetOpt($opt, $val)
    {
        if ($this->IsAvailable()) {
            if (ftp_set_option($this->_conn, $opt, $val)) {

                return true;
            }

            $this->AddError("Can't assign {$opt} = {$val}");
        }

        return false;
    }

    /**
     * Obtiene una opción FTP
     *
     * @param string $opt
     * @return mixed
     */
    public function GetOpt($opt)
    {
        if ($this->IsAvailable()) {
            $r = ftp_get_option($this->_conn, $opt);
            if ($r !== false) {

                return $r;
            }

            $this->AddError("Can't get option {$opt}");
        }

        return false;
    }

    public function Connect($host = null, $way = null, $port = null)
    {
        $this->Host($host);
        $this->Way($way);
        $this->Port($port);

        if ($this->IsAvailable()) {
            switch ($this->_way) {
                case self::CONNECTWAY_SSL:
                    $this->_conn = ftp_ssl_connect($this->_host, $this->_port, $this->_timeout);
                    break;
                case self::CONNECTWAY_NORMAL:
                default:
                    $this->_conn = ftp_connect($this->_host, $this->_port, $this->_timeout);
                    break;
            }
        }

        return $this->_conn !== false;
    }

    public function Login($user = null, $pass = null)
    {
        $this->User($user);
        $this->Pass($pass);

        if ($this->IsAvailable()) {
            return ftp_login($this->_conn, $this->_user, $this->_pass);
        }

        return false;
    }

    /**
     * Obtiene el texto del último error
     *
     * @param bool $conNum
     * @return string
     */
    public function Error($conNum = false)
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
        if (is_resource($this->_conn)) {
            return ftp_close($this->_conn);
        }

        return false;
    }

    /**
     * Registra un error
     *
     * @param string $txt
     * @param int $num
     */
    protected function AddError($txt, $num = 0)
    {
        $this->_errores[] = array(0 => $num, 1 => $txt);
    }

    public function Chdir($dir)
    {
        return ftp_chdir($this->_conn, $dir);
    }

    public function Get($local_file, $remote_file, $mode = FTP_BINARY)
    {
        return ftp_get($this->_conn, $local_file, $remote_file, $mode);
    }

    public function Nlist($dir)
    {
        return ftp_nlist($this->_conn, $dir);
    }

    public function DeleteFile($path)
    {
        return ftp_delete($this->_con, $path);
    }

    public function DeleteDir($dir)
    {
        return ftp_rmdir($this->_con, $dir);
    }

    public function Rmdir($dir)
    {
        return $this->DeleteDir($dir);
    }
}