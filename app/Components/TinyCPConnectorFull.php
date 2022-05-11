<?php
namespace App\Components;

class TinyCPConnectorFull
{
    //region Members
    private $host;
    private $port;
    private $login;
    private $password;

    private $privateKey;
    private $encryptionKey;
    //endregion

    //region Constructor
    function __construct(string $host, int $port) {

        if (strpos($host, 'http') === 0)
            $this->host = $host;
        else
            $this->host = 'http://' . $host;
        $this->port = $port;
    }
    //endregion

    //region Public Methods
    public function Auth($login, $password, $tfaCode = '') {

        $this->login = $login;
        $this->password = $password;

        $params = [];
            if ($tfaCode)
            $params['tfa_code'] = $tfaCode;

        $udt = $this->CallMethod('app/udt', $params, false);

        if ($udt)
        {
            $this->privateKey = sha1($udt . '|' . $login . '|' . sha1($password));
            $this->encryptionKey = str_pad(base64_encode(sha1($this->privateKey, true)), 32, '=');

            $resutl = $this->CallMethod('app/info', [], true);
            if(!$resutl['auth_status'])
                return false;

            return true;
        }
        else
        {
            return false;
        }
    }

    public function CallMethod(string $method, array $params = [], bool $encrypt = true) {

        $headers = [];
        if ($this->login && $this->privateKey)
            {
            if ($encrypt)
                    $headers[] = 'Content-Type: text/plain;charset=UTF-8';
                $headers[] = 'X-TINYCP-LOGIN: '. $this->login;
                $headers[] = 'X-TINYCP-SIGN: '.base64_encode($this->encrypt($this->login));
        }

        $body = null;
        if ($params) {
                $body = json_encode($params);

            if ($encrypt)
                    $body = utf8_encode($this->encrypt($body));
        }

        $url = $this->host . ':'. $this->port . "/api/$method";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);

        //region Parse headers into readable format
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers_string = substr($response, 0, $header_size);
        $header_arr = array_filter(explode("\r\n", $headers_string));

        $status_header = array_shift($header_arr);
        if (!preg_match('/^HTTP\/\d(\.\d)? [0-9]{3}/', $status_header))
        {
            throw new Exception('Invalid response header:'. $status_header);
        }
        list(, , $response_msg) = explode(' ', $status_header, 3);

        $response_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response_headers = [];

        foreach ($header_arr as $h)
        {
            if (!$h)
                continue;

            if (strpos($h, ':') === false)
                continue;

            list($key, $value) = explode(':', $h, 2);
            $response_headers[trim($key)] = trim($value);
        }
        //endregion

        $result = substr($response, $header_size);
        curl_close($ch);

        switch ($response_status)
        {
            case 200:
                if (array_key_exists('x-tinycp-encryption', $response_headers))
                    $result = $this->decrypt($result);

                $result = json_decode($result, true);
                return $result;

            case 401:
                $error = json_decode($response_msg, true);
                if ($error === false)
                    $error = ['msg' => 'Unauthorized', 'details' => ''];

                $msg = $error['msg'];
                if ($error['details'])
                    $msg.= ': ' . $error['details'];

                throw new Exception($msg);
            case 404:
                throw new Exception("Unknown route: $method");
            case 500:
                $error = json_decode($response_msg, true);
                if ($error === false)
                    $error = ['msg' => 'Internal server error', 'details' => ''];

                $msg = $error['msg'];
                if ($error['details'])
                    $msg.= ': ' . $error['details'];
                if ($error['unhandled'])
                    $msg .= '-- UNHANDLED';

                throw new Exception($msg);
            default:
                break;
        }

        return null;
    }
    //endregion

    //region Private Methods
    private function encrypt(string $text) {
        $encrypted = openssl_encrypt($text, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, '4863134076325584');
        return $encrypted;
    }

    private function decrypt(string $data) {
        $decrypted = openssl_decrypt($data, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, '4863134076325584');
        return $decrypted;
    }
    //endregion


    //region EndPoints
	// app/info
	public function app___info() {
		$params = [];
		return $this->CallMethod('app/info', $params, true);
	}

	// app/ws_send_msg
	public function app___ws_send_msg(string $msg, string $process_id = '', string $payload = '') {
		$params = ['msg' => $msg, 'process_id' => $process_id, 'payload' => $payload];
		return $this->CallMethod('app/ws_send_msg', $params, true);
	}

	// app/ws_disconnect_all
	public function app___ws_disconnect_all() {
		$params = [];
		return $this->CallMethod('app/ws_disconnect_all', $params, true);
	}

	// app/ping
	public function app___ping() {
		$params = [];
		return $this->CallMethod('app/ping', $params, true);
	}

	// app/udt
	public function app___udt(string $tfa_code = '') {
		$params = ['tfa_code' => $tfa_code];
		return $this->CallMethod('app/udt', $params, true);
	}

	// app/tfa
	public function app___tfa() {
		$params = [];
		return $this->CallMethod('app/tfa', $params, true);
	}

	// app/module_refresh
	public function app___module_refresh() {
		$params = [];
		return $this->CallMethod('app/module_refresh', $params, true);
	}

	// app/module_list
	public function app___module_list() {
		$params = [];
		return $this->CallMethod('app/module_list', $params, true);
	}

	// app/module_prepare
	public function app___module_prepare(string $module_id) {
		$params = ['module_id' => $module_id];
		return $this->CallMethod('app/module_prepare', $params, true);
	}

	// app/module_install
	public function app___module_install(string $module_id) {
		$params = ['module_id' => $module_id];
		return $this->CallMethod('app/module_install', $params, true);
	}

	// app/module_uninstall
	public function app___module_uninstall(string $module_id) {
		$params = ['module_id' => $module_id];
		return $this->CallMethod('app/module_uninstall', $params, true);
	}

	// app/module_enable
	public function app___module_enable(string $module_id) {
		$params = ['module_id' => $module_id];
		return $this->CallMethod('app/module_enable', $params, true);
	}

	// app/module_disable
	public function app___module_disable(string $module_id) {
		$params = ['module_id' => $module_id];
		return $this->CallMethod('app/module_disable', $params, true);
	}

	// app/os_info
	public function app___os_info() {
		$params = [];
		return $this->CallMethod('app/os_info', $params, true);
	}

	// app/os_shells
	public function app___os_shells() {
		$params = [];
		return $this->CallMethod('app/os_shells', $params, true);
	}

	// app/ip4_addresses
	public function app___ip4_addresses() {
		$params = [];
		return $this->CallMethod('app/ip4_addresses', $params, true);
	}

	// app/ip6_addresses
	public function app___ip6_addresses() {
		$params = [];
		return $this->CallMethod('app/ip6_addresses', $params, true);
	}

	// app/send_error_report
	public function app___send_error_report(string $message, string $details, int $type, string $server_version = '', string $client_version = '') {
		$params = ['message' => $message, 'details' => $details, 'type' => $type, 'server_version' => $server_version, 'client_version' => $client_version];
		return $this->CallMethod('app/send_error_report', $params, true);
	}

	// app/subscription_get
	public function app___subscription_get() {
		$params = [];
		return $this->CallMethod('app/subscription_get', $params, true);
	}

	// app/subscription_download
	public function app___subscription_download() {
		$params = [];
		return $this->CallMethod('app/subscription_download', $params, true);
	}

	// app/version_check
	public function app___version_check() {
		$params = [];
		return $this->CallMethod('app/version_check', $params, true);
	}

	// app/version_upgrade
	public function app___version_upgrade() {
		$params = [];
		return $this->CallMethod('app/version_upgrade', $params, true);
	}

	// app/user_list
	public function app___user_list() {
		$params = [];
		return $this->CallMethod('app/user_list', $params, true);
	}

	// app/user_get
	public function app___user_get(string $login) {
		$params = ['login' => $login];
		return $this->CallMethod('app/user_get', $params, true);
	}

	// app/user_add
	public function app___user_add(string $login, string $password) {
		$params = ['login' => $login, 'password' => $password];
		return $this->CallMethod('app/user_add', $params, true);
	}

	// app/user_delete
	public function app___user_delete(string $login) {
		$params = ['login' => $login];
		return $this->CallMethod('app/user_delete', $params, true);
	}

	// app/user_update
	public function app___user_update(string $login, string $new_login, string $new_password = '') {
		$params = ['login' => $login, 'new_login' => $new_login, 'new_password' => $new_password];
		return $this->CallMethod('app/user_update', $params, true);
	}

	// app/user_set_password
	public function app___user_set_password(string $login, string $password) {
		$params = ['login' => $login, 'password' => $password];
		return $this->CallMethod('app/user_set_password', $params, true);
	}

	// app/llui_access_list
	public function app___llui_access_list() {
		$params = [];
		return $this->CallMethod('app/llui_access_list', $params, true);
	}

	// app/llui_access_add
	public function app___llui_access_add(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('app/llui_access_add', $params, true);
	}

	// app/llui_access_delete
	public function app___llui_access_delete(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('app/llui_access_delete', $params, true);
	}

	// app/settings_get
	public function app___settings_get() {
		$params = [];
		return $this->CallMethod('app/settings_get', $params, true);
	}

	// app/settings_set
	public function app___settings_set(string $email = '', int $port = 80, array $dns_servers = []) {
		$params = ['email' => $email, 'port' => $port, 'dns_servers' => $dns_servers];
		return $this->CallMethod('app/settings_set', $params, true);
	}

	// app/tfa_get
	public function app___tfa_get() {
		$params = [];
		return $this->CallMethod('app/tfa_get', $params, true);
	}

	// app/tfa_set
	public function app___tfa_set(bool $enabled, string $secret) {
		$params = ['enabled' => $enabled, 'secret' => $secret];
		return $this->CallMethod('app/tfa_set', $params, true);
	}

	// app/tfa_generate_secret
	public function app___tfa_generate_secret() {
		$params = [];
		return $this->CallMethod('app/tfa_generate_secret', $params, true);
	}

	// connector/php
	public function connector___php() {
		$params = [];
		return $this->CallMethod('connector/php', $params, true);
	}

	// migration/import_info_plesk
	public function migration___import_info_plesk(string $ssh_host, int $ssh_port, string $user_name, string $password) {
		$params = ['ssh_host' => $ssh_host, 'ssh_port' => $ssh_port, 'user_name' => $user_name, 'password' => $password];
		return $this->CallMethod('migration/import_info_plesk', $params, true);
	}

	// migration/import_do_plesk
	public function migration___import_do_plesk(string $ssh_host, int $ssh_port, string $user_name, string $password, string $domain_name, string $type) {
		$params = ['ssh_host' => $ssh_host, 'ssh_port' => $ssh_port, 'user_name' => $user_name, 'password' => $password, 'domain_name' => $domain_name, 'type' => $type];
		return $this->CallMethod('migration/import_do_plesk', $params, true);
	}

	// migration/import_info_tinycp_v1
	public function migration___import_info_tinycp_v1(string $ssh_host, int $ssh_port, string $user_name, string $password) {
		$params = ['ssh_host' => $ssh_host, 'ssh_port' => $ssh_port, 'user_name' => $user_name, 'password' => $password];
		return $this->CallMethod('migration/import_info_tinycp_v1', $params, true);
	}

	// migration/import_do_tinycp_v1
	public function migration___import_do_tinycp_v1(string $ssh_host, int $ssh_port, string $user_name, string $password, string $domain_name, string $type) {
		$params = ['ssh_host' => $ssh_host, 'ssh_port' => $ssh_port, 'user_name' => $user_name, 'password' => $password, 'domain_name' => $domain_name, 'type' => $type];
		return $this->CallMethod('migration/import_do_tinycp_v1', $params, true);
	}

	// migration/import_info_tinycp_v2
	public function migration___import_info_tinycp_v2(string $ssh_host, int $ssh_port, string $user_name, string $password) {
		$params = ['ssh_host' => $ssh_host, 'ssh_port' => $ssh_port, 'user_name' => $user_name, 'password' => $password];
		return $this->CallMethod('migration/import_info_tinycp_v2', $params, true);
	}

	// migration/import_do_tinycp_v2
	public function migration___import_do_tinycp_v2(string $ssh_host, int $ssh_port, string $user_name, string $password, string $domain_name, string $type) {
		$params = ['ssh_host' => $ssh_host, 'ssh_port' => $ssh_port, 'user_name' => $user_name, 'password' => $password, 'domain_name' => $domain_name, 'type' => $type];
		return $this->CallMethod('migration/import_do_tinycp_v2', $params, true);
	}

	// migration/import_local
	public function migration___import_local() {
		$params = [];
		return $this->CallMethod('migration/import_local', $params, true);
	}

	// db/mariadb/import_from
	public function db___mariadb___import_from(string $src_host, int $src_port, string $src_db, string $src_user, string $src_pass, string $definer = '', bool $schema_only = false, bool $skip_large_data = false, int $skip_large_data_threshold = 0, string $dest_db = '') {
		$params = ['src_host' => $src_host, 'src_port' => $src_port, 'src_db' => $src_db, 'src_user' => $src_user, 'src_pass' => $src_pass, 'definer' => $definer, 'schema_only' => $schema_only, 'skip_large_data' => $skip_large_data, 'skip_large_data_threshold' => $skip_large_data_threshold, 'dest_db' => $dest_db];
		return $this->CallMethod('db/mariadb/import_from', $params, true);
	}

	// db/mariadb/remote_db_list
	public function db___mariadb___remote_db_list(string $host, int $port, string $user, string $pass = '') {
		$params = ['host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass];
		return $this->CallMethod('db/mariadb/remote_db_list', $params, true);
	}

	// db/mariadb/execute
	public function db___mariadb___execute(string $sql, string $db = '') {
		$params = ['sql' => $sql, 'db' => $db];
		return $this->CallMethod('db/mariadb/execute', $params, true);
	}

	// db/mariadb/dict
	public function db___mariadb___dict() {
		$params = [];
		return $this->CallMethod('db/mariadb/dict', $params, true);
	}

	// db/mariadb/info
	public function db___mariadb___info() {
		$params = [];
		return $this->CallMethod('db/mariadb/info', $params, true);
	}

	// db/mariadb/config_get
	public function db___mariadb___config_get() {
		$params = [];
		return $this->CallMethod('db/mariadb/config_get', $params, true);
	}

	// db/mariadb/config_set
	public function db___mariadb___config_set(array $variables) {
		$params = ['variables' => $variables];
		return $this->CallMethod('db/mariadb/config_set', $params, true);
	}

	// db/mariadb/db_list
	public function db___mariadb___db_list() {
		$params = [];
		return $this->CallMethod('db/mariadb/db_list', $params, true);
	}

	// db/mariadb/db_add
	public function db___mariadb___db_add(string $db, string $charset = 'utf8') {
		$params = ['db' => $db, 'charset' => $charset];
		return $this->CallMethod('db/mariadb/db_add', $params, true);
	}

	// db/mariadb/db_delete
	public function db___mariadb___db_delete(string $db) {
		$params = ['db' => $db];
		return $this->CallMethod('db/mariadb/db_delete', $params, true);
	}

	// db/mariadb/db_get
	public function db___mariadb___db_get(string $db) {
		$params = ['db' => $db];
		return $this->CallMethod('db/mariadb/db_get', $params, true);
	}

	// db/mariadb/db_tables_get
	public function db___mariadb___db_tables_get(string $db) {
		$params = ['db' => $db];
		return $this->CallMethod('db/mariadb/db_tables_get', $params, true);
	}

	// db/mariadb/db_upload_sql
	public function db___mariadb___db_upload_sql(string $file_name, string $args = '') {
		$params = ['file_name' => $file_name, 'args' => $args];
		return $this->CallMethod('db/mariadb/db_upload_sql', $params, true);
	}

	// db/mariadb/db_user_list
	public function db___mariadb___db_user_list(string $db) {
		$params = ['db' => $db];
		return $this->CallMethod('db/mariadb/db_user_list', $params, true);
	}

	// db/mariadb/db_user_get
	public function db___mariadb___db_user_get(string $db, string $user, string $host) {
		$params = ['db' => $db, 'user' => $user, 'host' => $host];
		return $this->CallMethod('db/mariadb/db_user_get', $params, true);
	}

	// db/mariadb/db_user_add
	public function db___mariadb___db_user_add(string $db, string $user, string $password, string $host, string $active_plugin = '') {
		$params = ['db' => $db, 'user' => $user, 'password' => $password, 'host' => $host, 'active_plugin' => $active_plugin];
		return $this->CallMethod('db/mariadb/db_user_add', $params, true);
	}

	// db/mariadb/db_user_set
	public function db___mariadb___db_user_set(string $db, string $user, string $host, string $password = '', string $plugin = '', bool $select_priv = true, bool $insert_priv = true, bool $update_priv = true, bool $delete_priv = true, bool $create_priv = true, bool $drop_priv = true, bool $grant_priv = true, bool $references_priv = true, bool $index_priv = true, bool $alter_priv = true, bool $create_tmp_table_priv = true, bool $lock_tables_priv = true, bool $create_view_priv = true, bool $show_view_priv = true, bool $create_routine_priv = true, bool $alter_routine_priv = true, bool $execute_priv = true, bool $event_priv = true, bool $trigger_priv = true) {
		$params = ['db' => $db, 'user' => $user, 'host' => $host, 'password' => $password, 'plugin' => $plugin, 'select_priv' => $select_priv, 'insert_priv' => $insert_priv, 'update_priv' => $update_priv, 'delete_priv' => $delete_priv, 'create_priv' => $create_priv, 'drop_priv' => $drop_priv, 'grant_priv' => $grant_priv, 'references_priv' => $references_priv, 'index_priv' => $index_priv, 'alter_priv' => $alter_priv, 'create_tmp_table_priv' => $create_tmp_table_priv, 'lock_tables_priv' => $lock_tables_priv, 'create_view_priv' => $create_view_priv, 'show_view_priv' => $show_view_priv, 'create_routine_priv' => $create_routine_priv, 'alter_routine_priv' => $alter_routine_priv, 'execute_priv' => $execute_priv, 'event_priv' => $event_priv, 'trigger_priv' => $trigger_priv];
		return $this->CallMethod('db/mariadb/db_user_set', $params, true);
	}

	// db/mariadb/db_user_delete
	public function db___mariadb___db_user_delete(string $db, string $user, string $host) {
		$params = ['db' => $db, 'user' => $user, 'host' => $host];
		return $this->CallMethod('db/mariadb/db_user_delete', $params, true);
	}

	// db/mariadb/global_user_list
	public function db___mariadb___global_user_list() {
		$params = [];
		return $this->CallMethod('db/mariadb/global_user_list', $params, true);
	}

	// db/mariadb/global_user_add
	public function db___mariadb___global_user_add(string $user, string $password, string $host, string $active_plugin = '') {
		$params = ['user' => $user, 'password' => $password, 'host' => $host, 'active_plugin' => $active_plugin];
		return $this->CallMethod('db/mariadb/global_user_add', $params, true);
	}

	// db/mariadb/global_user_delete
	public function db___mariadb___global_user_delete(string $user, string $host) {
		$params = ['user' => $user, 'host' => $host];
		return $this->CallMethod('db/mariadb/global_user_delete', $params, true);
	}

	// db/mariadb/global_user_get
	public function db___mariadb___global_user_get(string $user, string $host) {
		$params = ['user' => $user, 'host' => $host];
		return $this->CallMethod('db/mariadb/global_user_get', $params, true);
	}

	// db/mariadb/global_user_set
	public function db___mariadb___global_user_set(string $user, string $host, string $plugin = '', string $password = '', bool $select_priv = true, bool $insert_priv = true, bool $update_priv = true, bool $delete_priv = true, bool $create_priv = true, bool $drop_priv = true, bool $reload_priv = true, bool $shutdown_priv = true, bool $process_priv = true, bool $file_priv = true, bool $grant_priv = true, bool $references_priv = true, bool $index_priv = true, bool $alter_priv = true, bool $show_db_priv = true, bool $super_priv = true, bool $create_tmp_table_priv = true, bool $lock_tables_priv = true, bool $execute_priv = true, bool $repl_slave_priv = true, bool $repl_client_priv = true, bool $create_view_priv = true, bool $show_view_priv = true, bool $create_routine_priv = true, bool $alter_routine_priv = true, bool $create_user_priv = true, bool $event_priv = true, bool $trigger_priv = true, bool $create_tablespace_priv = true) {
		$params = ['user' => $user, 'host' => $host, 'plugin' => $plugin, 'password' => $password, 'select_priv' => $select_priv, 'insert_priv' => $insert_priv, 'update_priv' => $update_priv, 'delete_priv' => $delete_priv, 'create_priv' => $create_priv, 'drop_priv' => $drop_priv, 'reload_priv' => $reload_priv, 'shutdown_priv' => $shutdown_priv, 'process_priv' => $process_priv, 'file_priv' => $file_priv, 'grant_priv' => $grant_priv, 'references_priv' => $references_priv, 'index_priv' => $index_priv, 'alter_priv' => $alter_priv, 'show_db_priv' => $show_db_priv, 'super_priv' => $super_priv, 'create_tmp_table_priv' => $create_tmp_table_priv, 'lock_tables_priv' => $lock_tables_priv, 'execute_priv' => $execute_priv, 'repl_slave_priv' => $repl_slave_priv, 'repl_client_priv' => $repl_client_priv, 'create_view_priv' => $create_view_priv, 'show_view_priv' => $show_view_priv, 'create_routine_priv' => $create_routine_priv, 'alter_routine_priv' => $alter_routine_priv, 'create_user_priv' => $create_user_priv, 'event_priv' => $event_priv, 'trigger_priv' => $trigger_priv, 'create_tablespace_priv' => $create_tablespace_priv];
		return $this->CallMethod('db/mariadb/global_user_set', $params, true);
	}

	// db/mariadb/backup_task_list
	public function db___mariadb___backup_task_list() {
		$params = [];
		return $this->CallMethod('db/mariadb/backup_task_list', $params, true);
	}

	// db/mariadb/backup_task_get
	public function db___mariadb___backup_task_get(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('db/mariadb/backup_task_get', $params, true);
	}

	// db/mariadb/backup_task_add
	public function db___mariadb___backup_task_add(string $name, string $db, string $schedule, array $skip_tables, bool $gzip, string $dest_dir, bool $delete_old, int $store_days, string $post_cmd = '') {
		$params = ['name' => $name, 'db' => $db, 'schedule' => $schedule, 'skip_tables' => $skip_tables, 'gzip' => $gzip, 'dest_dir' => $dest_dir, 'delete_old' => $delete_old, 'store_days' => $store_days, 'post_cmd' => $post_cmd];
		return $this->CallMethod('db/mariadb/backup_task_add', $params, true);
	}

	// db/mariadb/backup_task_update
	public function db___mariadb___backup_task_update(string $name, string $db, string $schedule, array $skip_tables, bool $gzip, string $dest_dir, bool $delete_old, int $store_days, string $post_cmd = '') {
		$params = ['name' => $name, 'db' => $db, 'schedule' => $schedule, 'skip_tables' => $skip_tables, 'gzip' => $gzip, 'dest_dir' => $dest_dir, 'delete_old' => $delete_old, 'store_days' => $store_days, 'post_cmd' => $post_cmd];
		return $this->CallMethod('db/mariadb/backup_task_update', $params, true);
	}

	// db/mariadb/backup_task_delete
	public function db___mariadb___backup_task_delete(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('db/mariadb/backup_task_delete', $params, true);
	}

	// db/mariadb/backup_task_launch
	public function db___mariadb___backup_task_launch(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('db/mariadb/backup_task_launch', $params, true);
	}

	// db/mariadb/restart
	public function db___mariadb___restart() {
		$params = [];
		return $this->CallMethod('db/mariadb/restart', $params, true);
	}

	// files/sambadlna/info
	public function files___sambadlna___info() {
		$params = [];
		return $this->CallMethod('files/sambadlna/info', $params, true);
	}

	// files/sambadlna/config_get
	public function files___sambadlna___config_get() {
		$params = [];
		return $this->CallMethod('files/sambadlna/config_get', $params, true);
	}

	// files/sambadlna/config_set
	public function files___sambadlna___config_set(string $netbios_name, string $netbios_workgroup, string $dlna_name) {
		$params = ['netbios_name' => $netbios_name, 'netbios_workgroup' => $netbios_workgroup, 'dlna_name' => $dlna_name];
		return $this->CallMethod('files/sambadlna/config_set', $params, true);
	}

	// files/sambadlna/user_list
	public function files___sambadlna___user_list() {
		$params = [];
		return $this->CallMethod('files/sambadlna/user_list', $params, true);
	}

	// files/sambadlna/user_get
	public function files___sambadlna___user_get(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('files/sambadlna/user_get', $params, true);
	}

	// files/sambadlna/user_add
	public function files___sambadlna___user_add(string $display_name, string $password, array $shares) {
		$params = ['display_name' => $display_name, 'password' => $password, 'shares' => $shares];
		return $this->CallMethod('files/sambadlna/user_add', $params, true);
	}

	// files/sambadlna/user_delete
	public function files___sambadlna___user_delete(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('files/sambadlna/user_delete', $params, true);
	}

	// files/sambadlna/user_update
	public function files___sambadlna___user_update(string $name, string $display_name, array $shares, string $password = '') {
		$params = ['name' => $name, 'display_name' => $display_name, 'shares' => $shares, 'password' => $password];
		return $this->CallMethod('files/sambadlna/user_update', $params, true);
	}

	// files/sambadlna/share_list
	public function files___sambadlna___share_list() {
		$params = [];
		return $this->CallMethod('files/sambadlna/share_list', $params, true);
	}

	// files/sambadlna/share_get
	public function files___sambadlna___share_get(string $share_name) {
		$params = ['share_name' => $share_name];
		return $this->CallMethod('files/sambadlna/share_get', $params, true);
	}

	// files/sambadlna/share_add
	public function files___sambadlna___share_add(string $share_name, string $directory) {
		$params = ['share_name' => $share_name, 'directory' => $directory];
		return $this->CallMethod('files/sambadlna/share_add', $params, true);
	}

	// files/sambadlna/share_delete
	public function files___sambadlna___share_delete(string $share_name) {
		$params = ['share_name' => $share_name];
		return $this->CallMethod('files/sambadlna/share_delete', $params, true);
	}

	// files/sambadlna/share_update
	public function files___sambadlna___share_update(string $share_name, string $path, bool $is_public, bool $is_browseable, bool $is_readonly, string $dlna, array $users) {
		$params = ['share_name' => $share_name, 'path' => $path, 'is_public' => $is_public, 'is_browseable' => $is_browseable, 'is_readonly' => $is_readonly, 'dlna' => $dlna, 'users' => $users];
		return $this->CallMethod('files/sambadlna/share_update', $params, true);
	}

	// files/sambadlna/reload
	public function files___sambadlna___reload() {
		$params = [];
		return $this->CallMethod('files/sambadlna/reload', $params, true);
	}

	// files/sambadlna/restart
	public function files___sambadlna___restart() {
		$params = [];
		return $this->CallMethod('files/sambadlna/restart', $params, true);
	}

	// files/vsftpd/dict
	public function files___vsftpd___dict() {
		$params = [];
		return $this->CallMethod('files/vsftpd/dict', $params, true);
	}

	// files/vsftpd/account_add
	public function files___vsftpd___account_add(string $user_name, string $password, string $dir) {
		$params = ['user_name' => $user_name, 'password' => $password, 'dir' => $dir];
		return $this->CallMethod('files/vsftpd/account_add', $params, true);
	}

	// files/vsftpd/account_edit
	public function files___vsftpd___account_edit(string $user_name, string $password = '', string $dir = '') {
		$params = ['user_name' => $user_name, 'password' => $password, 'dir' => $dir];
		return $this->CallMethod('files/vsftpd/account_edit', $params, true);
	}

	// files/vsftpd/account_delete
	public function files___vsftpd___account_delete(string $user_name) {
		$params = ['user_name' => $user_name];
		return $this->CallMethod('files/vsftpd/account_delete', $params, true);
	}

	// files/vsftpd/account_get
	public function files___vsftpd___account_get(string $user_name) {
		$params = ['user_name' => $user_name];
		return $this->CallMethod('files/vsftpd/account_get', $params, true);
	}

	// files/vsftpd/account_list
	public function files___vsftpd___account_list() {
		$params = [];
		return $this->CallMethod('files/vsftpd/account_list', $params, true);
	}

	// files/vsftpd/settings_get
	public function files___vsftpd___settings_get() {
		$params = [];
		return $this->CallMethod('files/vsftpd/settings_get', $params, true);
	}

	// files/vsftpd/settings_set
	public function files___vsftpd___settings_set(string $ftpd_banner = '', bool $ssl_enable = false) {
		$params = ['ftpd_banner' => $ftpd_banner, 'ssl_enable' => $ssl_enable];
		return $this->CallMethod('files/vsftpd/settings_set', $params, true);
	}

	// files/vsftpd/restart
	public function files___vsftpd___restart() {
		$params = [];
		return $this->CallMethod('files/vsftpd/restart', $params, true);
	}

	// files/vsftpd/reload
	public function files___vsftpd___reload() {
		$params = [];
		return $this->CallMethod('files/vsftpd/reload', $params, true);
	}

	// mail/mailserver/guard_blacklist
	public function mail___mailserver___guard_blacklist(string $address) {
		$params = ['address' => $address];
		return $this->CallMethod('mail/mailserver/guard_blacklist', $params, true);
	}

	// mail/mailserver/guard_whitelist
	public function mail___mailserver___guard_whitelist(string $address) {
		$params = ['address' => $address];
		return $this->CallMethod('mail/mailserver/guard_whitelist', $params, true);
	}

	// mail/mailserver/guard_clear
	public function mail___mailserver___guard_clear(string $address) {
		$params = ['address' => $address];
		return $this->CallMethod('mail/mailserver/guard_clear', $params, true);
	}

	// mail/mailserver/guard_ban
	public function mail___mailserver___guard_ban(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('mail/mailserver/guard_ban', $params, true);
	}

	// mail/mailserver/guard_unban
	public function mail___mailserver___guard_unban(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('mail/mailserver/guard_unban', $params, true);
	}

	// mail/mailserver/guard_ban_list
	public function mail___mailserver___guard_ban_list() {
		$params = [];
		return $this->CallMethod('mail/mailserver/guard_ban_list', $params, true);
	}

	// mail/mailserver/dict
	public function mail___mailserver___dict() {
		$params = [];
		return $this->CallMethod('mail/mailserver/dict', $params, true);
	}

	// mail/mailserver/server_settings_get
	public function mail___mailserver___server_settings_get() {
		$params = [];
		return $this->CallMethod('mail/mailserver/server_settings_get', $params, true);
	}

	// mail/mailserver/server_settings_edit
	public function mail___mailserver___server_settings_edit(array $whitelist, array $blacklist, array $rbl, array $forbidden_file_ext, bool $require_spf = true, bool $require_dkim = false, bool $require_valid_dkim = true, string $antivirus = '', int $message_size_limit_mb = 0, int $timeout_frozen_after_days = 2) {
		$params = ['whitelist' => $whitelist, 'blacklist' => $blacklist, 'rbl' => $rbl, 'forbidden_file_ext' => $forbidden_file_ext, 'require_spf' => $require_spf, 'require_dkim' => $require_dkim, 'require_valid_dkim' => $require_valid_dkim, 'antivirus' => $antivirus, 'message_size_limit_mb' => $message_size_limit_mb, 'timeout_frozen_after_days' => $timeout_frozen_after_days];
		return $this->CallMethod('mail/mailserver/server_settings_edit', $params, true);
	}

	// mail/mailserver/domain_settings_get
	public function mail___mailserver___domain_settings_get(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('mail/mailserver/domain_settings_get', $params, true);
	}

	// mail/mailserver/domain_settings_edit
	public function mail___mailserver___domain_settings_edit(string $domain_name, string $catch_all_email = '', string $outgoing_ip = '') {
		$params = ['domain_name' => $domain_name, 'catch_all_email' => $catch_all_email, 'outgoing_ip' => $outgoing_ip];
		return $this->CallMethod('mail/mailserver/domain_settings_edit', $params, true);
	}

	// mail/mailserver/dns_records_info
	public function mail___mailserver___dns_records_info(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('mail/mailserver/dns_records_info', $params, true);
	}

	// mail/mailserver/email_get
	public function mail___mailserver___email_get(string $email) {
		$params = ['email' => $email];
		return $this->CallMethod('mail/mailserver/email_get', $params, true);
	}

	// mail/mailserver/email_get
	public function mail___mailserver___email_get2(string $domain_name, string $name) {
		$params = ['domain_name' => $domain_name, 'name' => $name];
		return $this->CallMethod('mail/mailserver/email_get', $params, true);
	}

	// mail/mailserver/email_add
	public function mail___mailserver___email_add(string $email, string $password) {
		$params = ['email' => $email, 'password' => $password];
		return $this->CallMethod('mail/mailserver/email_add', $params, true);
	}

	// mail/mailserver/email_add
	public function mail___mailserver___email_add2(string $domain_name, string $name, string $password) {
		$params = ['domain_name' => $domain_name, 'name' => $name, 'password' => $password];
		return $this->CallMethod('mail/mailserver/email_add', $params, true);
	}

	// mail/mailserver/email_delete
	public function mail___mailserver___email_delete(string $domain_name, string $name) {
		$params = ['domain_name' => $domain_name, 'name' => $name];
		return $this->CallMethod('mail/mailserver/email_delete', $params, true);
	}

	// mail/mailserver/email_delete
	public function mail___mailserver___email_delete2(string $email) {
		$params = ['email' => $email];
		return $this->CallMethod('mail/mailserver/email_delete', $params, true);
	}

	// mail/mailserver/email_edit
	public function mail___mailserver___email_edit(string $domain_name, string $name, bool $enabled = true, array $alt_names = [], bool $responder_enabled = false, string $responder_subject = '', string $responder_body = '', string $responder_start = '', string $responder_end = '', bool $forward_enabled = false, array $forward_emails = [], array $copy_sent_emails = [], string $description = '') {
		$params = ['domain_name' => $domain_name, 'name' => $name, 'enabled' => $enabled, 'alt_names' => $alt_names, 'responder_enabled' => $responder_enabled, 'responder_subject' => $responder_subject, 'responder_body' => $responder_body, 'responder_start' => $responder_start, 'responder_end' => $responder_end, 'forward_enabled' => $forward_enabled, 'forward_emails' => $forward_emails, 'copy_sent_emails' => $copy_sent_emails, 'description' => $description];
		return $this->CallMethod('mail/mailserver/email_edit', $params, true);
	}

	// mail/mailserver/email_edit
	public function mail___mailserver___email_edit2(string $email, bool $enabled = true, array $alt_names = [], bool $responder_enabled = false, string $responder_subject = '', string $responder_body = '', string $responder_start = '', string $responder_end = '', bool $forward_enabled = false, array $forward_emails = [], array $copy_sent_emails = [], string $description = '') {
		$params = ['email' => $email, 'enabled' => $enabled, 'alt_names' => $alt_names, 'responder_enabled' => $responder_enabled, 'responder_subject' => $responder_subject, 'responder_body' => $responder_body, 'responder_start' => $responder_start, 'responder_end' => $responder_end, 'forward_enabled' => $forward_enabled, 'forward_emails' => $forward_emails, 'copy_sent_emails' => $copy_sent_emails, 'description' => $description];
		return $this->CallMethod('mail/mailserver/email_edit', $params, true);
	}

	// mail/mailserver/email_pass_change
	public function mail___mailserver___email_pass_change(string $domain_name, string $name, string $password) {
		$params = ['domain_name' => $domain_name, 'name' => $name, 'password' => $password];
		return $this->CallMethod('mail/mailserver/email_pass_change', $params, true);
	}

	// mail/mailserver/email_pass_change
	public function mail___mailserver___email_pass_change2(string $email, string $password) {
		$params = ['email' => $email, 'password' => $password];
		return $this->CallMethod('mail/mailserver/email_pass_change', $params, true);
	}

	// mail/mailserver/email_list
	public function mail___mailserver___email_list(string $domain_name = '') {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('mail/mailserver/email_list', $params, true);
	}

	// mail/mailserver/import_from
	public function mail___mailserver___import_from(string $host, string $user_name, string $password, bool $include_messages) {
		$params = ['host' => $host, 'user_name' => $user_name, 'password' => $password, 'include_messages' => $include_messages];
		return $this->CallMethod('mail/mailserver/import_from', $params, true);
	}

	// mail/exim4/queue_list
	public function mail___exim4___queue_list(bool $latest) {
		$params = ['latest' => $latest];
		return $this->CallMethod('mail/exim4/queue_list', $params, true);
	}

	// mail/exim4/queue_remove
	public function mail___exim4___queue_remove(string $message_id) {
		$params = ['message_id' => $message_id];
		return $this->CallMethod('mail/exim4/queue_remove', $params, true);
	}

	// mail/exim4/queue_remove
	public function mail___exim4___queue_remove2(array $message_ids) {
		$params = ['message_ids' => $message_ids];
		return $this->CallMethod('mail/exim4/queue_remove', $params, true);
	}

	// mail/exim4/queue_remove_all
	public function mail___exim4___queue_remove_all() {
		$params = [];
		return $this->CallMethod('mail/exim4/queue_remove_all', $params, true);
	}

	// mail/exim4/queue_resend
	public function mail___exim4___queue_resend(string $message_id) {
		$params = ['message_id' => $message_id];
		return $this->CallMethod('mail/exim4/queue_resend', $params, true);
	}

	// mail/exim4/queue_resend
	public function mail___exim4___queue_resend2(array $message_ids) {
		$params = ['message_ids' => $message_ids];
		return $this->CallMethod('mail/exim4/queue_resend', $params, true);
	}

	// mail/exim4/queue_resend_all
	public function mail___exim4___queue_resend_all() {
		$params = [];
		return $this->CallMethod('mail/exim4/queue_resend_all', $params, true);
	}

	// mail/exim4/queue_message
	public function mail___exim4___queue_message(string $message_id) {
		$params = ['message_id' => $message_id];
		return $this->CallMethod('mail/exim4/queue_message', $params, true);
	}

	// mail/exim4/logs
	public function mail___exim4___logs() {
		$params = [];
		return $this->CallMethod('mail/exim4/logs', $params, true);
	}

	// mail/exim4/restart
	public function mail___exim4___restart() {
		$params = [];
		return $this->CallMethod('mail/exim4/restart', $params, true);
	}

	// mail/exim4/reload
	public function mail___exim4___reload() {
		$params = [];
		return $this->CallMethod('mail/exim4/reload', $params, true);
	}

	// mail/exim4/guard_blacklist
	public function mail___exim4___guard_blacklist(string $address) {
		$params = ['address' => $address];
		return $this->CallMethod('mail/exim4/guard_blacklist', $params, true);
	}

	// mail/exim4/guard_whitelist
	public function mail___exim4___guard_whitelist(string $address) {
		$params = ['address' => $address];
		return $this->CallMethod('mail/exim4/guard_whitelist', $params, true);
	}

	// mail/exim4/guard_clear
	public function mail___exim4___guard_clear(string $address) {
		$params = ['address' => $address];
		return $this->CallMethod('mail/exim4/guard_clear', $params, true);
	}

	// mail/exim4/guard_ban
	public function mail___exim4___guard_ban(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('mail/exim4/guard_ban', $params, true);
	}

	// mail/exim4/guard_unban
	public function mail___exim4___guard_unban(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('mail/exim4/guard_unban', $params, true);
	}

	// mail/exim4/guard_ban_list
	public function mail___exim4___guard_ban_list() {
		$params = [];
		return $this->CallMethod('mail/exim4/guard_ban_list', $params, true);
	}

	// mail/exim4/dict
	public function mail___exim4___dict() {
		$params = [];
		return $this->CallMethod('mail/exim4/dict', $params, true);
	}

	// mail/exim4/server_settings_get
	public function mail___exim4___server_settings_get() {
		$params = [];
		return $this->CallMethod('mail/exim4/server_settings_get', $params, true);
	}

	// mail/exim4/server_settings_edit
	public function mail___exim4___server_settings_edit(array $whitelist, array $blacklist, array $rbl, array $forbidden_file_ext, bool $require_spf = true, bool $require_dkim = false, bool $require_valid_dkim = true, string $antivirus = '', int $message_size_limit_mb = 0, int $timeout_frozen_after_days = 2) {
		$params = ['whitelist' => $whitelist, 'blacklist' => $blacklist, 'rbl' => $rbl, 'forbidden_file_ext' => $forbidden_file_ext, 'require_spf' => $require_spf, 'require_dkim' => $require_dkim, 'require_valid_dkim' => $require_valid_dkim, 'antivirus' => $antivirus, 'message_size_limit_mb' => $message_size_limit_mb, 'timeout_frozen_after_days' => $timeout_frozen_after_days];
		return $this->CallMethod('mail/exim4/server_settings_edit', $params, true);
	}

	// mail/exim4/domain_settings_get
	public function mail___exim4___domain_settings_get(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('mail/exim4/domain_settings_get', $params, true);
	}

	// mail/exim4/domain_settings_edit
	public function mail___exim4___domain_settings_edit(string $domain_name, string $catch_all_email = '', string $outgoing_ip = '') {
		$params = ['domain_name' => $domain_name, 'catch_all_email' => $catch_all_email, 'outgoing_ip' => $outgoing_ip];
		return $this->CallMethod('mail/exim4/domain_settings_edit', $params, true);
	}

	// mail/exim4/dns_records_info
	public function mail___exim4___dns_records_info(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('mail/exim4/dns_records_info', $params, true);
	}

	// mail/exim4/email_get
	public function mail___exim4___email_get(string $email) {
		$params = ['email' => $email];
		return $this->CallMethod('mail/exim4/email_get', $params, true);
	}

	// mail/exim4/email_get
	public function mail___exim4___email_get2(string $domain_name, string $name) {
		$params = ['domain_name' => $domain_name, 'name' => $name];
		return $this->CallMethod('mail/exim4/email_get', $params, true);
	}

	// mail/exim4/email_add
	public function mail___exim4___email_add(string $email, string $password) {
		$params = ['email' => $email, 'password' => $password];
		return $this->CallMethod('mail/exim4/email_add', $params, true);
	}

	// mail/exim4/email_add
	public function mail___exim4___email_add2(string $domain_name, string $name, string $password) {
		$params = ['domain_name' => $domain_name, 'name' => $name, 'password' => $password];
		return $this->CallMethod('mail/exim4/email_add', $params, true);
	}

	// mail/exim4/email_delete
	public function mail___exim4___email_delete(string $domain_name, string $name) {
		$params = ['domain_name' => $domain_name, 'name' => $name];
		return $this->CallMethod('mail/exim4/email_delete', $params, true);
	}

	// mail/exim4/email_delete
	public function mail___exim4___email_delete2(string $email) {
		$params = ['email' => $email];
		return $this->CallMethod('mail/exim4/email_delete', $params, true);
	}

	// mail/exim4/email_edit
	public function mail___exim4___email_edit(string $domain_name, string $name, bool $enabled = true, array $alt_names = [], bool $responder_enabled = false, string $responder_subject = '', string $responder_body = '', string $responder_start = '', string $responder_end = '', bool $forward_enabled = false, array $forward_emails = [], array $copy_sent_emails = [], string $description = '') {
		$params = ['domain_name' => $domain_name, 'name' => $name, 'enabled' => $enabled, 'alt_names' => $alt_names, 'responder_enabled' => $responder_enabled, 'responder_subject' => $responder_subject, 'responder_body' => $responder_body, 'responder_start' => $responder_start, 'responder_end' => $responder_end, 'forward_enabled' => $forward_enabled, 'forward_emails' => $forward_emails, 'copy_sent_emails' => $copy_sent_emails, 'description' => $description];
		return $this->CallMethod('mail/exim4/email_edit', $params, true);
	}

	// mail/exim4/email_edit
	public function mail___exim4___email_edit2(string $email, bool $enabled = true, array $alt_names = [], bool $responder_enabled = false, string $responder_subject = '', string $responder_body = '', string $responder_start = '', string $responder_end = '', bool $forward_enabled = false, array $forward_emails = [], array $copy_sent_emails = [], string $description = '') {
		$params = ['email' => $email, 'enabled' => $enabled, 'alt_names' => $alt_names, 'responder_enabled' => $responder_enabled, 'responder_subject' => $responder_subject, 'responder_body' => $responder_body, 'responder_start' => $responder_start, 'responder_end' => $responder_end, 'forward_enabled' => $forward_enabled, 'forward_emails' => $forward_emails, 'copy_sent_emails' => $copy_sent_emails, 'description' => $description];
		return $this->CallMethod('mail/exim4/email_edit', $params, true);
	}

	// mail/exim4/email_pass_change
	public function mail___exim4___email_pass_change(string $domain_name, string $name, string $password) {
		$params = ['domain_name' => $domain_name, 'name' => $name, 'password' => $password];
		return $this->CallMethod('mail/exim4/email_pass_change', $params, true);
	}

	// mail/exim4/email_pass_change
	public function mail___exim4___email_pass_change2(string $email, string $password) {
		$params = ['email' => $email, 'password' => $password];
		return $this->CallMethod('mail/exim4/email_pass_change', $params, true);
	}

	// mail/exim4/email_list
	public function mail___exim4___email_list(string $domain_name = '') {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('mail/exim4/email_list', $params, true);
	}

	// mail/exim4/import_from
	public function mail___exim4___import_from(string $host, string $user_name, string $password, bool $include_messages) {
		$params = ['host' => $host, 'user_name' => $user_name, 'password' => $password, 'include_messages' => $include_messages];
		return $this->CallMethod('mail/exim4/import_from', $params, true);
	}

	// system/files/dir_autocomplete
	public function system___files___dir_autocomplete(string $path) {
		$params = ['path' => $path];
		return $this->CallMethod('system/files/dir_autocomplete', $params, true);
	}

	// system/files/dir
	public function system___files___dir(string $path) {
		$params = ['path' => $path];
		return $this->CallMethod('system/files/dir', $params, true);
	}

	// system/files/move
	public function system___files___move(string $file, string $dest_dir) {
		$params = ['file' => $file, 'dest_dir' => $dest_dir];
		return $this->CallMethod('system/files/move', $params, true);
	}

	// system/files/move
	public function system___files___move2(array $files, string $dest_dir) {
		$params = ['files' => $files, 'dest_dir' => $dest_dir];
		return $this->CallMethod('system/files/move', $params, true);
	}

	// system/files/copy
	public function system___files___copy(string $file, string $dest_dir) {
		$params = ['file' => $file, 'dest_dir' => $dest_dir];
		return $this->CallMethod('system/files/copy', $params, true);
	}

	// system/files/copy
	public function system___files___copy2(array $files, string $dest_dir) {
		$params = ['files' => $files, 'dest_dir' => $dest_dir];
		return $this->CallMethod('system/files/copy', $params, true);
	}

	// system/files/rename
	public function system___files___rename(string $file, string $file_new) {
		$params = ['file' => $file, 'file_new' => $file_new];
		return $this->CallMethod('system/files/rename', $params, true);
	}

	// system/files/mkdir
	public function system___files___mkdir(string $dir) {
		$params = ['dir' => $dir];
		return $this->CallMethod('system/files/mkdir', $params, true);
	}

	// system/files/remove
	public function system___files___remove(string $file) {
		$params = ['file' => $file];
		return $this->CallMethod('system/files/remove', $params, true);
	}

	// system/files/remove
	public function system___files___remove2(array $files) {
		$params = ['files' => $files];
		return $this->CallMethod('system/files/remove', $params, true);
	}

	// system/files/chmod
	public function system___files___chmod(array $files, array $chmod, bool $recursively) {
		$params = ['files' => $files, 'chmod' => $chmod, 'recursively' => $recursively];
		return $this->CallMethod('system/files/chmod', $params, true);
	}

	// system/files/download
	public function system___files___download(string $file_name) {
		$params = ['file_name' => $file_name];
		return $this->CallMethod('system/files/download', $params, true);
	}

	// system/files/upload
	public function system___files___upload(string $file_name) {
		$params = ['file_name' => $file_name];
		return $this->CallMethod('system/files/upload', $params, true);
	}

	// system/files/chmod
	public function system___files___chmod2(array $files, bool $give_x_to_dirs_up, bool $give_x_to_dirs_down) {
		$params = ['files' => $files, 'give_x_to_dirs_up' => $give_x_to_dirs_up, 'give_x_to_dirs_down' => $give_x_to_dirs_down];
		return $this->CallMethod('system/files/chmod', $params, true);
	}

	// system/files/chown
	public function system___files___chown(array $files, string $user, string $group, bool $recursively) {
		$params = ['files' => $files, 'user' => $user, 'group' => $group, 'recursively' => $recursively];
		return $this->CallMethod('system/files/chown', $params, true);
	}

	// system/group/list
	public function system___group___list() {
		$params = [];
		return $this->CallMethod('system/group/list', $params, true);
	}

	// system/group/get
	public function system___group___get(string $group_name) {
		$params = ['group_name' => $group_name];
		return $this->CallMethod('system/group/get', $params, true);
	}

	// system/group/add
	public function system___group___add(string $group_name) {
		$params = ['group_name' => $group_name];
		return $this->CallMethod('system/group/add', $params, true);
	}

	// system/group/delete
	public function system___group___delete(string $group_name) {
		$params = ['group_name' => $group_name];
		return $this->CallMethod('system/group/delete', $params, true);
	}

	// system/guardian/dict
	public function system___guardian___dict() {
		$params = [];
		return $this->CallMethod('system/guardian/dict', $params, true);
	}

	// system/guardian/mail_ban_list
	public function system___guardian___mail_ban_list() {
		$params = [];
		return $this->CallMethod('system/guardian/mail_ban_list', $params, true);
	}

	// system/guardian/mail_unban
	public function system___guardian___mail_unban(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('system/guardian/mail_unban', $params, true);
	}

	// system/guardian/mail_unban_all
	public function system___guardian___mail_unban_all() {
		$params = [];
		return $this->CallMethod('system/guardian/mail_unban_all', $params, true);
	}

	// system/guardian/system_ban_list
	public function system___guardian___system_ban_list() {
		$params = [];
		return $this->CallMethod('system/guardian/system_ban_list', $params, true);
	}

	// system/guardian/system_unban
	public function system___guardian___system_unban(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('system/guardian/system_unban', $params, true);
	}

	// system/guardian/system_unban_all
	public function system___guardian___system_unban_all() {
		$params = [];
		return $this->CallMethod('system/guardian/system_unban_all', $params, true);
	}

	// system/guardian/web_ban_list
	public function system___guardian___web_ban_list() {
		$params = [];
		return $this->CallMethod('system/guardian/web_ban_list', $params, true);
	}

	// system/guardian/web_unban
	public function system___guardian___web_unban(string $ip) {
		$params = ['ip' => $ip];
		return $this->CallMethod('system/guardian/web_unban', $params, true);
	}

	// system/guardian/web_unban_all
	public function system___guardian___web_unban_all() {
		$params = [];
		return $this->CallMethod('system/guardian/web_unban_all', $params, true);
	}

	// system/monitor/data
	public function system___monitor___data() {
		$params = [];
		return $this->CallMethod('system/monitor/data', $params, true);
	}

	// system/monitor/w
	public function system___monitor___w() {
		$params = [];
		return $this->CallMethod('system/monitor/w', $params, true);
	}

	// system/monitor/sysload
	public function system___monitor___sysload() {
		$params = [];
		return $this->CallMethod('system/monitor/sysload', $params, true);
	}

	// system/monitor/disks
	public function system___monitor___disks() {
		$params = [];
		return $this->CallMethod('system/monitor/disks', $params, true);
	}

	// system/monitor/network_clients
	public function system___monitor___network_clients() {
		$params = [];
		return $this->CallMethod('system/monitor/network_clients', $params, true);
	}

	// system/monitor/info
	public function system___monitor___info() {
		$params = [];
		return $this->CallMethod('system/monitor/info', $params, true);
	}

	// system/monitor/kill_proc
	public function system___monitor___kill_proc(string $pid) {
		$params = ['pid' => $pid];
		return $this->CallMethod('system/monitor/kill_proc', $params, true);
	}

	// system/packagemanager/packages_list_installed
	public function system___packagemanager___packages_list_installed() {
		$params = [];
		return $this->CallMethod('system/packagemanager/packages_list_installed', $params, true);
	}

	// system/packagemanager/packages_list_upgradeable
	public function system___packagemanager___packages_list_upgradeable() {
		$params = [];
		return $this->CallMethod('system/packagemanager/packages_list_upgradeable', $params, true);
	}

	// system/packagemanager/install
	public function system___packagemanager___install(string $package_name) {
		$params = ['package_name' => $package_name];
		return $this->CallMethod('system/packagemanager/install', $params, true);
	}

	// system/packagemanager/uninstall
	public function system___packagemanager___uninstall(string $package_name) {
		$params = ['package_name' => $package_name];
		return $this->CallMethod('system/packagemanager/uninstall', $params, true);
	}

	// system/packagemanager/upgrade
	public function system___packagemanager___upgrade(string $package_name) {
		$params = ['package_name' => $package_name];
		return $this->CallMethod('system/packagemanager/upgrade', $params, true);
	}

	// system/packagemanager/upgrade_all
	public function system___packagemanager___upgrade_all() {
		$params = [];
		return $this->CallMethod('system/packagemanager/upgrade_all', $params, true);
	}

	// system/packagemanager/changelog
	public function system___packagemanager___changelog(string $package_name) {
		$params = ['package_name' => $package_name];
		return $this->CallMethod('system/packagemanager/changelog', $params, true);
	}

	// system/packagemanager/search
	public function system___packagemanager___search(string $text) {
		$params = ['text' => $text];
		return $this->CallMethod('system/packagemanager/search', $params, true);
	}

	// system/settings/info
	public function system___settings___info() {
		$params = [];
		return $this->CallMethod('system/settings/info', $params, true);
	}

	// system/settings/dns_servers_get
	public function system___settings___dns_servers_get() {
		$params = [];
		return $this->CallMethod('system/settings/dns_servers_get', $params, true);
	}

	// system/settings/dns_servers_set
	public function system___settings___dns_servers_set(array $dns_servers = []) {
		$params = ['dns_servers' => $dns_servers];
		return $this->CallMethod('system/settings/dns_servers_set', $params, true);
	}

	// system/settings/timezone_list
	public function system___settings___timezone_list() {
		$params = [];
		return $this->CallMethod('system/settings/timezone_list', $params, true);
	}

	// system/settings/timezone_get
	public function system___settings___timezone_get() {
		$params = [];
		return $this->CallMethod('system/settings/timezone_get', $params, true);
	}

	// system/settings/timezone_set
	public function system___settings___timezone_set(string $timezone) {
		$params = ['timezone' => $timezone];
		return $this->CallMethod('system/settings/timezone_set', $params, true);
	}

	// system/user/list
	public function system___user___list() {
		$params = [];
		return $this->CallMethod('system/user/list', $params, true);
	}

	// system/user/get
	public function system___user___get(string $user_name) {
		$params = ['user_name' => $user_name];
		return $this->CallMethod('system/user/get', $params, true);
	}

	// system/user/add
	public function system___user___add(string $user_name) {
		$params = ['user_name' => $user_name];
		return $this->CallMethod('system/user/add', $params, true);
	}

	// system/user/delete
	public function system___user___delete(string $user_name) {
		$params = ['user_name' => $user_name];
		return $this->CallMethod('system/user/delete', $params, true);
	}

	// system/user/update_gecos
	public function system___user___update_gecos(string $user_name, string $full_name = '', string $room_number = '', string $work_phone = '', string $home_phone = '', string $other = '') {
		$params = ['user_name' => $user_name, 'full_name' => $full_name, 'room_number' => $room_number, 'work_phone' => $work_phone, 'home_phone' => $home_phone, 'other' => $other];
		return $this->CallMethod('system/user/update_gecos', $params, true);
	}

	// system/user/update_password
	public function system___user___update_password(string $user_name, string $password = '') {
		$params = ['user_name' => $user_name, 'password' => $password];
		return $this->CallMethod('system/user/update_password', $params, true);
	}

	// system/user/add_to_group
	public function system___user___add_to_group(string $user_name, string $group_name) {
		$params = ['user_name' => $user_name, 'group_name' => $group_name];
		return $this->CallMethod('system/user/add_to_group', $params, true);
	}

	// system/user/remove_from_group
	public function system___user___remove_from_group(string $user_name, string $group_name) {
		$params = ['user_name' => $user_name, 'group_name' => $group_name];
		return $this->CallMethod('system/user/remove_from_group', $params, true);
	}

	// system/user/sshd_settings_update
	public function system___user___sshd_settings_update(string $user_name, string $shell_mode, string $auth_method) {
		$params = ['user_name' => $user_name, 'shell_mode' => $shell_mode, 'auth_method' => $auth_method];
		return $this->CallMethod('system/user/sshd_settings_update', $params, true);
	}

	// system/user/authorized_key_list
	public function system___user___authorized_key_list(string $user_name) {
		$params = ['user_name' => $user_name];
		return $this->CallMethod('system/user/authorized_key_list', $params, true);
	}

	// system/user/authorized_key_get
	public function system___user___authorized_key_get(string $user_name, string $key_id) {
		$params = ['user_name' => $user_name, 'key_id' => $key_id];
		return $this->CallMethod('system/user/authorized_key_get', $params, true);
	}

	// system/user/authorized_key_show_public
	public function system___user___authorized_key_show_public(string $user_name, string $key_id) {
		$params = ['user_name' => $user_name, 'key_id' => $key_id];
		return $this->CallMethod('system/user/authorized_key_show_public', $params, true);
	}

	// system/user/authorized_key_show_private
	public function system___user___authorized_key_show_private(string $user_name, string $key_id) {
		$params = ['user_name' => $user_name, 'key_id' => $key_id];
		return $this->CallMethod('system/user/authorized_key_show_private', $params, true);
	}

	// system/user/authorized_key_delete
	public function system___user___authorized_key_delete(string $user_name, string $key_id) {
		$params = ['user_name' => $user_name, 'key_id' => $key_id];
		return $this->CallMethod('system/user/authorized_key_delete', $params, true);
	}

	// system/user/authorized_key_update
	public function system___user___authorized_key_update(string $user_name, string $key_id, string $options = '', string $comment = '') {
		$params = ['user_name' => $user_name, 'key_id' => $key_id, 'options' => $options, 'comment' => $comment];
		return $this->CallMethod('system/user/authorized_key_update', $params, true);
	}

	// system/user/authorized_key_generate
	public function system___user___authorized_key_generate(string $user_name, string $type, string $passphrase = '', string $comment = '') {
		$params = ['user_name' => $user_name, 'type' => $type, 'passphrase' => $passphrase, 'comment' => $comment];
		return $this->CallMethod('system/user/authorized_key_generate', $params, true);
	}

	// system/user/authorized_key_append_line
	public function system___user___authorized_key_append_line(string $user_name, string $line) {
		$params = ['user_name' => $user_name, 'line' => $line];
		return $this->CallMethod('system/user/authorized_key_append_line', $params, true);
	}

	// system/user/authorized_key_upload
	public function system___user___authorized_key_upload(string $file_name, string $args) {
		$params = ['file_name' => $file_name, 'args' => $args];
		return $this->CallMethod('system/user/authorized_key_upload', $params, true);
	}

	// system/cron/list
	public function system___cron___list() {
		$params = [];
		return $this->CallMethod('system/cron/list', $params, true);
	}

	// system/cron/add
	public function system___cron___add(string $schedule, string $payload, string $type = '', string $user = 'root') {
		$params = ['schedule' => $schedule, 'payload' => $payload, 'type' => $type, 'user' => $user];
		return $this->CallMethod('system/cron/add', $params, true);
	}

	// system/cron/update
	public function system___cron___update(string $name, string $schedule, string $payload, string $type = '', string $user = 'root') {
		$params = ['name' => $name, 'schedule' => $schedule, 'payload' => $payload, 'type' => $type, 'user' => $user];
		return $this->CallMethod('system/cron/update', $params, true);
	}

	// system/cron/delete
	public function system___cron___delete(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('system/cron/delete', $params, true);
	}

	// system/docker/image_list
	public function system___docker___image_list() {
		$params = [];
		return $this->CallMethod('system/docker/image_list', $params, true);
	}

	// system/docker/image_pull
	public function system___docker___image_pull(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('system/docker/image_pull', $params, true);
	}

	// system/docker/image_build
	public function system___docker___image_build(string $name, string $context_dir, string $docker_script) {
		$params = ['name' => $name, 'context_dir' => $context_dir, 'docker_script' => $docker_script];
		return $this->CallMethod('system/docker/image_build', $params, true);
	}

	// system/docker/image_delete
	public function system___docker___image_delete(string $id) {
		$params = ['id' => $id];
		return $this->CallMethod('system/docker/image_delete', $params, true);
	}

	// system/docker/container_create
	public function system___docker___container_create(string $name, string $image_id, bool $tty = false) {
		$params = ['name' => $name, 'image_id' => $image_id, 'tty' => $tty];
		return $this->CallMethod('system/docker/container_create', $params, true);
	}

	// system/docker/container_delete
	public function system___docker___container_delete(string $id) {
		$params = ['id' => $id];
		return $this->CallMethod('system/docker/container_delete', $params, true);
	}

	// system/docker/container_list
	public function system___docker___container_list() {
		$params = [];
		return $this->CallMethod('system/docker/container_list', $params, true);
	}

	// system/iptables/service_rule_list
	public function system___iptables___service_rule_list() {
		$params = [];
		return $this->CallMethod('system/iptables/service_rule_list', $params, true);
	}

	// system/iptables/service_rule_update
	public function system___iptables___service_rule_update(string $ip, int $port, string $rule) {
		$params = ['ip' => $ip, 'port' => $port, 'rule' => $rule];
		return $this->CallMethod('system/iptables/service_rule_update', $params, true);
	}

	// system/iptables/manual_rule_list
	public function system___iptables___manual_rule_list() {
		$params = [];
		return $this->CallMethod('system/iptables/manual_rule_list', $params, true);
	}

	// system/iptables/manual_rule_add
	public function system___iptables___manual_rule_add(string $rule, string $ip, int $port_from, int $port_to = -1) {
		$params = ['rule' => $rule, 'ip' => $ip, 'port_from' => $port_from, 'port_to' => $port_to];
		return $this->CallMethod('system/iptables/manual_rule_add', $params, true);
	}

	// system/iptables/manual_rule_delete
	public function system___iptables___manual_rule_delete(string $rule, string $ip, string $dports) {
		$params = ['rule' => $rule, 'ip' => $ip, 'dports' => $dports];
		return $this->CallMethod('system/iptables/manual_rule_delete', $params, true);
	}

	// system/iptables/manual_rule_delete
	public function system___iptables___manual_rule_delete2(string $rule, string $ip, int $port_from, int $port_to = -1) {
		$params = ['rule' => $rule, 'ip' => $ip, 'port_from' => $port_from, 'port_to' => $port_to];
		return $this->CallMethod('system/iptables/manual_rule_delete', $params, true);
	}

	// system/iptables/manual_rule_delete
	public function system___iptables___manual_rule_delete3(string $id) {
		$params = ['id' => $id];
		return $this->CallMethod('system/iptables/manual_rule_delete', $params, true);
	}

	// system/iptables/redirect_list
	public function system___iptables___redirect_list() {
		$params = [];
		return $this->CallMethod('system/iptables/redirect_list', $params, true);
	}

	// system/iptables/redirect_add
	public function system___iptables___redirect_add(string $src_ip, int $src_port, string $dest_ip, int $dest_port) {
		$params = ['src_ip' => $src_ip, 'src_port' => $src_port, 'dest_ip' => $dest_ip, 'dest_port' => $dest_port];
		return $this->CallMethod('system/iptables/redirect_add', $params, true);
	}

	// system/iptables/redirect_delete
	public function system___iptables___redirect_delete(string $src_ip, int $src_port, string $dest_ip, int $dest_port) {
		$params = ['src_ip' => $src_ip, 'src_port' => $src_port, 'dest_ip' => $dest_ip, 'dest_port' => $dest_port];
		return $this->CallMethod('system/iptables/redirect_delete', $params, true);
	}

	// system/iptables/redirect_delete
	public function system___iptables___redirect_delete2(string $id) {
		$params = ['id' => $id];
		return $this->CallMethod('system/iptables/redirect_delete', $params, true);
	}

	// system/lxd/dicts
	public function system___lxd___dicts() {
		$params = [];
		return $this->CallMethod('system/lxd/dicts', $params, true);
	}

	// system/lxd/container_list
	public function system___lxd___container_list() {
		$params = [];
		return $this->CallMethod('system/lxd/container_list', $params, true);
	}

	// system/lxd/container_get
	public function system___lxd___container_get(string $container_name) {
		$params = ['container_name' => $container_name];
		return $this->CallMethod('system/lxd/container_get', $params, true);
	}

	// system/lxd/container_add
	public function system___lxd___container_add(string $container_name, string $image, string $disk_type = 'btrfs', int $disk_size_gb = 2, int $disk_write_mb = 15, int $disk_read_mb = 20, int $network_ip4 = 2, int $network_mbit = 10) {
		$params = ['container_name' => $container_name, 'image' => $image, 'disk_type' => $disk_type, 'disk_size_gb' => $disk_size_gb, 'disk_write_mb' => $disk_write_mb, 'disk_read_mb' => $disk_read_mb, 'network_ip4' => $network_ip4, 'network_mbit' => $network_mbit];
		return $this->CallMethod('system/lxd/container_add', $params, true);
	}

	// system/lxd/container_edit
	public function system___lxd___container_edit(string $container_name, int $disk_write_mb = 150, int $disk_read_mb = 200, int $network_mbit = 20) {
		$params = ['container_name' => $container_name, 'disk_write_mb' => $disk_write_mb, 'disk_read_mb' => $disk_read_mb, 'network_mbit' => $network_mbit];
		return $this->CallMethod('system/lxd/container_edit', $params, true);
	}

	// system/lxd/container_delete
	public function system___lxd___container_delete(string $container_name) {
		$params = ['container_name' => $container_name];
		return $this->CallMethod('system/lxd/container_delete', $params, true);
	}

	// system/lxd/container_start
	public function system___lxd___container_start(string $container_name) {
		$params = ['container_name' => $container_name];
		return $this->CallMethod('system/lxd/container_start', $params, true);
	}

	// system/lxd/container_stop
	public function system___lxd___container_stop(string $container_name) {
		$params = ['container_name' => $container_name];
		return $this->CallMethod('system/lxd/container_stop', $params, true);
	}

	// system/lxd/snapshot_add
	public function system___lxd___snapshot_add(string $container_name, string $snapshot_name) {
		$params = ['container_name' => $container_name, 'snapshot_name' => $snapshot_name];
		return $this->CallMethod('system/lxd/snapshot_add', $params, true);
	}

	// system/lxd/snapshot_delete
	public function system___lxd___snapshot_delete(string $container_name, string $snapshot_name) {
		$params = ['container_name' => $container_name, 'snapshot_name' => $snapshot_name];
		return $this->CallMethod('system/lxd/snapshot_delete', $params, true);
	}

	// system/lxd/snapshot_restore
	public function system___lxd___snapshot_restore(string $container_name, string $snapshot_name) {
		$params = ['container_name' => $container_name, 'snapshot_name' => $snapshot_name];
		return $this->CallMethod('system/lxd/snapshot_restore', $params, true);
	}

	// system/lxd/cron_add_snapshot
	public function system___lxd___cron_add_snapshot(string $container_name, string $schedule) {
		$params = ['container_name' => $container_name, 'schedule' => $schedule];
		return $this->CallMethod('system/lxd/cron_add_snapshot', $params, true);
	}

	// system/lxd/cron_add_restore
	public function system___lxd___cron_add_restore(string $container_name, string $snapshot_name, string $schedule) {
		$params = ['container_name' => $container_name, 'snapshot_name' => $snapshot_name, 'schedule' => $schedule];
		return $this->CallMethod('system/lxd/cron_add_restore', $params, true);
	}

	// system/lxd/cron_delete
	public function system___lxd___cron_delete(string $container_name, string $cron_name) {
		$params = ['container_name' => $container_name, 'cron_name' => $cron_name];
		return $this->CallMethod('system/lxd/cron_delete', $params, true);
	}

	// system/lxd/redirect_list
	public function system___lxd___redirect_list(string $container_name) {
		$params = ['container_name' => $container_name];
		return $this->CallMethod('system/lxd/redirect_list', $params, true);
	}

	// system/lxd/redirect_add
	public function system___lxd___redirect_add(string $container_name, string $src_ip, int $src_port, int $dest_port) {
		$params = ['container_name' => $container_name, 'src_ip' => $src_ip, 'src_port' => $src_port, 'dest_port' => $dest_port];
		return $this->CallMethod('system/lxd/redirect_add', $params, true);
	}

	// system/lxd/redirect_delete
	public function system___lxd___redirect_delete(string $container_name, string $src_ip, int $src_port, int $dest_port) {
		$params = ['container_name' => $container_name, 'src_ip' => $src_ip, 'src_port' => $src_port, 'dest_port' => $dest_port];
		return $this->CallMethod('system/lxd/redirect_delete', $params, true);
	}

	// system/lxd/redirect_delete
	public function system___lxd___redirect_delete2(string $rule_id) {
		$params = ['rule_id' => $rule_id];
		return $this->CallMethod('system/lxd/redirect_delete', $params, true);
	}

	// system/openvpn/config_get
	public function system___openvpn___config_get() {
		$params = [];
		return $this->CallMethod('system/openvpn/config_get', $params, true);
	}

	// system/openvpn/config_set
	public function system___openvpn___config_set(string $ip, int $range_start = 2, int $range_end = 255) {
		$params = ['ip' => $ip, 'range_start' => $range_start, 'range_end' => $range_end];
		return $this->CallMethod('system/openvpn/config_set', $params, true);
	}

	// system/openvpn/client_add
	public function system___openvpn___client_add(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('system/openvpn/client_add', $params, true);
	}

	// system/openvpn/client_get
	public function system___openvpn___client_get(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('system/openvpn/client_get', $params, true);
	}

	// system/openvpn/client_update
	public function system___openvpn___client_update(string $name, int $ip_suffix) {
		$params = ['name' => $name, 'ip_suffix' => $ip_suffix];
		return $this->CallMethod('system/openvpn/client_update', $params, true);
	}

	// system/openvpn/client_delete
	public function system___openvpn___client_delete(string $name) {
		$params = ['name' => $name];
		return $this->CallMethod('system/openvpn/client_delete', $params, true);
	}

	// system/openvpn/client_download_ovpn
	public function system___openvpn___client_download_ovpn(string $client_name) {
		$params = ['client_name' => $client_name];
		return $this->CallMethod('system/openvpn/client_download_ovpn', $params, true);
	}

	// system/openvpn/client_list
	public function system___openvpn___client_list() {
		$params = [];
		return $this->CallMethod('system/openvpn/client_list', $params, true);
	}

	// system/openvpn/connection_list
	public function system___openvpn___connection_list() {
		$params = [];
		return $this->CallMethod('system/openvpn/connection_list', $params, true);
	}

	// system/openvpn/connection_delete
	public function system___openvpn___connection_delete(string $connection_name) {
		$params = ['connection_name' => $connection_name];
		return $this->CallMethod('system/openvpn/connection_delete', $params, true);
	}

	// system/openvpn/connection_reconnect
	public function system___openvpn___connection_reconnect(string $connection_name) {
		$params = ['connection_name' => $connection_name];
		return $this->CallMethod('system/openvpn/connection_reconnect', $params, true);
	}

	// system/openvpn/connection_upload_ovpn
	public function system___openvpn___connection_upload_ovpn(string $file_name) {
		$params = ['file_name' => $file_name];
		return $this->CallMethod('system/openvpn/connection_upload_ovpn', $params, true);
	}

	// system/openvpn/reload
	public function system___openvpn___reload() {
		$params = [];
		return $this->CallMethod('system/openvpn/reload', $params, true);
	}

	// system/openvpn/restart
	public function system___openvpn___restart() {
		$params = [];
		return $this->CallMethod('system/openvpn/restart', $params, true);
	}

	// web/http/dicts
	public function web___http___dicts() {
		$params = [];
		return $this->CallMethod('web/http/dicts', $params, true);
	}

	// web/http/domain_name_list
	public function web___http___domain_name_list(string $parent_domain_name = '') {
		$params = ['parent_domain_name' => $parent_domain_name];
		return $this->CallMethod('web/http/domain_name_list', $params, true);
	}

	// web/http/domain_list
	public function web___http___domain_list(bool $root_only = true) {
		$params = ['root_only' => $root_only];
		return $this->CallMethod('web/http/domain_list', $params, true);
	}

	// web/http/domain_get
	public function web___http___domain_get(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/domain_get', $params, true);
	}

	// web/http/domain_add
	public function web___http___domain_add(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/domain_add', $params, true);
	}

	// web/http/domain_restore
	public function web___http___domain_restore(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/domain_restore', $params, true);
	}

	// web/http/domain_restorable_list
	public function web___http___domain_restorable_list() {
		$params = [];
		return $this->CallMethod('web/http/domain_restorable_list', $params, true);
	}

	// web/http/domain_restorable_purge
	public function web___http___domain_restorable_purge() {
		$params = [];
		return $this->CallMethod('web/http/domain_restorable_purge', $params, true);
	}

	// web/http/domain_edit
	public function web___http___domain_edit(string $domain_name, string $document_root = '', bool $enabled = true, string $certificate = '', int $redirect_ssl = -1, int $redirect_www = -1, int $timeout = -1, string $php = '', string $apache_custom_config = '', string $nginx_custom_config = '') {
		$params = ['domain_name' => $domain_name, 'document_root' => $document_root, 'enabled' => $enabled, 'certificate' => $certificate, 'redirect_ssl' => $redirect_ssl, 'redirect_www' => $redirect_www, 'timeout' => $timeout, 'php' => $php, 'apache_custom_config' => $apache_custom_config, 'nginx_custom_config' => $nginx_custom_config];
		return $this->CallMethod('web/http/domain_edit', $params, true);
	}

	// web/http/domain_delete
	public function web___http___domain_delete(string $domain_name, bool $recursive) {
		$params = ['domain_name' => $domain_name, 'recursive' => $recursive];
		return $this->CallMethod('web/http/domain_delete', $params, true);
	}

	// web/http/domain_host_list
	public function web___http___domain_host_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/domain_host_list', $params, true);
	}

	// web/http/domain_host_add
	public function web___http___domain_host_add(string $domain_name, string $ip, int $port, bool $ssl) {
		$params = ['domain_name' => $domain_name, 'ip' => $ip, 'port' => $port, 'ssl' => $ssl];
		return $this->CallMethod('web/http/domain_host_add', $params, true);
	}

	// web/http/domain_host_delete
	public function web___http___domain_host_delete(string $domain_name, string $ip, int $port, bool $ssl) {
		$params = ['domain_name' => $domain_name, 'ip' => $ip, 'port' => $port, 'ssl' => $ssl];
		return $this->CallMethod('web/http/domain_host_delete', $params, true);
	}

	// web/http/domain_proxy_list
	public function web___http___domain_proxy_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/domain_proxy_list', $params, true);
	}

	// web/http/domain_proxy_add
	public function web___http___domain_proxy_add(string $domain_name, string $from, string $to) {
		$params = ['domain_name' => $domain_name, 'from' => $from, 'to' => $to];
		return $this->CallMethod('web/http/domain_proxy_add', $params, true);
	}

	// web/http/domain_proxy_delete
	public function web___http___domain_proxy_delete(string $domain_name, string $from, string $to) {
		$params = ['domain_name' => $domain_name, 'from' => $from, 'to' => $to];
		return $this->CallMethod('web/http/domain_proxy_delete', $params, true);
	}

	// web/http/domain_alias_list
	public function web___http___domain_alias_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/domain_alias_list', $params, true);
	}

	// web/http/domain_alias_add
	public function web___http___domain_alias_add(string $domain_name, string $alias) {
		$params = ['domain_name' => $domain_name, 'alias' => $alias];
		return $this->CallMethod('web/http/domain_alias_add', $params, true);
	}

	// web/http/domain_alias_delete
	public function web___http___domain_alias_delete(string $domain_name, string $alias) {
		$params = ['domain_name' => $domain_name, 'alias' => $alias];
		return $this->CallMethod('web/http/domain_alias_delete', $params, true);
	}

	// web/http/domain_cert_add
	public function web___http___domain_cert_add(string $domain_name, string $cert_name, string $private_key, string $crt, string $csr = '', string $ca = '') {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'private_key' => $private_key, 'crt' => $crt, 'csr' => $csr, 'ca' => $ca];
		return $this->CallMethod('web/http/domain_cert_add', $params, true);
	}

	// web/http/domain_cert_edit
	public function web___http___domain_cert_edit(string $domain_name, string $cert_name, string $private_key, string $crt, string $csr = '', string $ca = '') {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'private_key' => $private_key, 'crt' => $crt, 'csr' => $csr, 'ca' => $ca];
		return $this->CallMethod('web/http/domain_cert_edit', $params, true);
	}

	// web/http/domain_cert_get
	public function web___http___domain_cert_get(string $domain_name, string $cert_name, bool $extended = true) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'extended' => $extended];
		return $this->CallMethod('web/http/domain_cert_get', $params, true);
	}

	// web/http/domain_cert_name_list
	public function web___http___domain_cert_name_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/domain_cert_name_list', $params, true);
	}

	// web/http/domain_cert_list
	public function web___http___domain_cert_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/domain_cert_list', $params, true);
	}

	// web/http/domain_cert_delete
	public function web___http___domain_cert_delete(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/http/domain_cert_delete', $params, true);
	}

	// web/http/domain_cert_show_crt
	public function web___http___domain_cert_show_crt(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/http/domain_cert_show_crt', $params, true);
	}

	// web/http/domain_cert_show_csr
	public function web___http___domain_cert_show_csr(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/http/domain_cert_show_csr', $params, true);
	}

	// web/http/domain_cert_show_key
	public function web___http___domain_cert_show_key(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/http/domain_cert_show_key', $params, true);
	}

	// web/http/domain_cert_show_ca
	public function web___http___domain_cert_show_ca(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/http/domain_cert_show_ca', $params, true);
	}

	// web/http/cert_verify
	public function web___http___cert_verify(string $private_key, string $csr, string $crt, string $ca = '') {
		$params = ['private_key' => $private_key, 'csr' => $csr, 'crt' => $crt, 'ca' => $ca];
		return $this->CallMethod('web/http/cert_verify', $params, true);
	}

	// web/http/cert_generate_selfsigned
	public function web___http___cert_generate_selfsigned(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/http/cert_generate_selfsigned', $params, true);
	}

	// web/http/dns_ovh_read
	public function web___http___dns_ovh_read() {
		$params = [];
		return $this->CallMethod('web/http/dns_ovh_read', $params, true);
	}

	// web/http/import_from
	public function web___http___import_from(string $host, string $user_name, string $password, bool $include_www) {
		$params = ['host' => $host, 'user_name' => $user_name, 'password' => $password, 'include_www' => $include_www];
		return $this->CallMethod('web/http/import_from', $params, true);
	}

	// web/apache/domain_edit
	public function web___apache___domain_edit(string $domain_name, string $document_root = '', bool $enabled = true, string $certificate = '', int $redirect_ssl = -1, int $redirect_www = -1, int $timeout = -1, string $php = '', string $apache_custom_config = '') {
		$params = ['domain_name' => $domain_name, 'document_root' => $document_root, 'enabled' => $enabled, 'certificate' => $certificate, 'redirect_ssl' => $redirect_ssl, 'redirect_www' => $redirect_www, 'timeout' => $timeout, 'php' => $php, 'apache_custom_config' => $apache_custom_config];
		return $this->CallMethod('web/apache/domain_edit', $params, true);
	}

	// web/apache/domain_show_log_access
	public function web___apache___domain_show_log_access(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_show_log_access', $params, true);
	}

	// web/apache/domain_show_log_error
	public function web___apache___domain_show_log_error(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_show_log_error', $params, true);
	}

	// web/apache/domain_show_config
	public function web___apache___domain_show_config(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_show_config', $params, true);
	}

	// web/apache/module_list
	public function web___apache___module_list() {
		$params = [];
		return $this->CallMethod('web/apache/module_list', $params, true);
	}

	// web/apache/module_enable
	public function web___apache___module_enable(string $mod_name) {
		$params = ['mod_name' => $mod_name];
		return $this->CallMethod('web/apache/module_enable', $params, true);
	}

	// web/apache/module_disable
	public function web___apache___module_disable(string $mod_name) {
		$params = ['mod_name' => $mod_name];
		return $this->CallMethod('web/apache/module_disable', $params, true);
	}

	// web/apache/svn_get
	public function web___apache___svn_get(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/svn_get', $params, true);
	}

	// web/apache/svn_set
	public function web___apache___svn_set(string $domain_name, bool $enabled, string $title, string $repos_path, string $users_file, string $accesses_file) {
		$params = ['domain_name' => $domain_name, 'enabled' => $enabled, 'title' => $title, 'repos_path' => $repos_path, 'users_file' => $users_file, 'accesses_file' => $accesses_file];
		return $this->CallMethod('web/apache/svn_set', $params, true);
	}

	// web/apache/svn_user_add
	public function web___apache___svn_user_add(string $domain_name, string $user_name, string $password) {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name, 'password' => $password];
		return $this->CallMethod('web/apache/svn_user_add', $params, true);
	}

	// web/apache/svn_user_delete
	public function web___apache___svn_user_delete(string $domain_name, string $user_name) {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name];
		return $this->CallMethod('web/apache/svn_user_delete', $params, true);
	}

	// web/apache/svn_user_change_pass
	public function web___apache___svn_user_change_pass(string $domain_name, string $user_name, string $password) {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name, 'password' => $password];
		return $this->CallMethod('web/apache/svn_user_change_pass', $params, true);
	}

	// web/apache/svn_user_get
	public function web___apache___svn_user_get(string $domain_name, string $user_name) {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name];
		return $this->CallMethod('web/apache/svn_user_get', $params, true);
	}

	// web/apache/svn_user_edit
	public function web___apache___svn_user_edit(string $domain_name, string $user_name, array $repo_accesses, string $password = '') {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name, 'repo_accesses' => $repo_accesses, 'password' => $password];
		return $this->CallMethod('web/apache/svn_user_edit', $params, true);
	}

	// web/apache/svn_repo_get
	public function web___apache___svn_repo_get(string $domain_name, string $repo_name) {
		$params = ['domain_name' => $domain_name, 'repo_name' => $repo_name];
		return $this->CallMethod('web/apache/svn_repo_get', $params, true);
	}

	// web/apache/svn_repo_add
	public function web___apache___svn_repo_add(string $domain_name, string $repo_name) {
		$params = ['domain_name' => $domain_name, 'repo_name' => $repo_name];
		return $this->CallMethod('web/apache/svn_repo_add', $params, true);
	}

	// web/apache/svn_repo_delete
	public function web___apache___svn_repo_delete(string $domain_name, string $repo_name) {
		$params = ['domain_name' => $domain_name, 'repo_name' => $repo_name];
		return $this->CallMethod('web/apache/svn_repo_delete', $params, true);
	}

	// web/apache/svn_repo_edit
	public function web___apache___svn_repo_edit(string $domain_name, string $repo_name, array $user_accesses) {
		$params = ['domain_name' => $domain_name, 'repo_name' => $repo_name, 'user_accesses' => $user_accesses];
		return $this->CallMethod('web/apache/svn_repo_edit', $params, true);
	}

	// web/apache/git_get
	public function web___apache___git_get(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/git_get', $params, true);
	}

	// web/apache/git_set
	public function web___apache___git_set(string $domain_name, bool $enabled, string $title, string $repos_path, string $users_file, string $accesses_file) {
		$params = ['domain_name' => $domain_name, 'enabled' => $enabled, 'title' => $title, 'repos_path' => $repos_path, 'users_file' => $users_file, 'accesses_file' => $accesses_file];
		return $this->CallMethod('web/apache/git_set', $params, true);
	}

	// web/apache/git_user_add
	public function web___apache___git_user_add(string $domain_name, string $user_name, string $password) {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name, 'password' => $password];
		return $this->CallMethod('web/apache/git_user_add', $params, true);
	}

	// web/apache/git_user_delete
	public function web___apache___git_user_delete(string $domain_name, string $user_name) {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name];
		return $this->CallMethod('web/apache/git_user_delete', $params, true);
	}

	// web/apache/git_user_change_pass
	public function web___apache___git_user_change_pass(string $domain_name, string $user_name, string $password) {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name, 'password' => $password];
		return $this->CallMethod('web/apache/git_user_change_pass', $params, true);
	}

	// web/apache/git_user_get
	public function web___apache___git_user_get(string $domain_name, string $user_name) {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name];
		return $this->CallMethod('web/apache/git_user_get', $params, true);
	}

	// web/apache/git_user_edit
	public function web___apache___git_user_edit(string $domain_name, string $user_name, array $repo_accesses, string $password = '') {
		$params = ['domain_name' => $domain_name, 'user_name' => $user_name, 'repo_accesses' => $repo_accesses, 'password' => $password];
		return $this->CallMethod('web/apache/git_user_edit', $params, true);
	}

	// web/apache/git_repo_get
	public function web___apache___git_repo_get(string $domain_name, string $repo_name) {
		$params = ['domain_name' => $domain_name, 'repo_name' => $repo_name];
		return $this->CallMethod('web/apache/git_repo_get', $params, true);
	}

	// web/apache/git_repo_add
	public function web___apache___git_repo_add(string $domain_name, string $repo_name) {
		$params = ['domain_name' => $domain_name, 'repo_name' => $repo_name];
		return $this->CallMethod('web/apache/git_repo_add', $params, true);
	}

	// web/apache/git_repo_delete
	public function web___apache___git_repo_delete(string $domain_name, string $repo_name) {
		$params = ['domain_name' => $domain_name, 'repo_name' => $repo_name];
		return $this->CallMethod('web/apache/git_repo_delete', $params, true);
	}

	// web/apache/git_repo_edit
	public function web___apache___git_repo_edit(string $domain_name, string $repo_name, array $user_accesses) {
		$params = ['domain_name' => $domain_name, 'repo_name' => $repo_name, 'user_accesses' => $user_accesses];
		return $this->CallMethod('web/apache/git_repo_edit', $params, true);
	}

	// web/apache/restart
	public function web___apache___restart() {
		$params = [];
		return $this->CallMethod('web/apache/restart', $params, true);
	}

	// web/apache/reload
	public function web___apache___reload() {
		$params = [];
		return $this->CallMethod('web/apache/reload', $params, true);
	}

	// web/apache/dicts
	public function web___apache___dicts() {
		$params = [];
		return $this->CallMethod('web/apache/dicts', $params, true);
	}

	// web/apache/domain_name_list
	public function web___apache___domain_name_list(string $parent_domain_name = '') {
		$params = ['parent_domain_name' => $parent_domain_name];
		return $this->CallMethod('web/apache/domain_name_list', $params, true);
	}

	// web/apache/domain_list
	public function web___apache___domain_list(bool $root_only = true) {
		$params = ['root_only' => $root_only];
		return $this->CallMethod('web/apache/domain_list', $params, true);
	}

	// web/apache/domain_get
	public function web___apache___domain_get(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_get', $params, true);
	}

	// web/apache/domain_add
	public function web___apache___domain_add(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_add', $params, true);
	}

	// web/apache/domain_restore
	public function web___apache___domain_restore(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_restore', $params, true);
	}

	// web/apache/domain_restorable_list
	public function web___apache___domain_restorable_list() {
		$params = [];
		return $this->CallMethod('web/apache/domain_restorable_list', $params, true);
	}

	// web/apache/domain_restorable_purge
	public function web___apache___domain_restorable_purge() {
		$params = [];
		return $this->CallMethod('web/apache/domain_restorable_purge', $params, true);
	}

	// web/apache/domain_edit
	public function web___apache___domain_edit2(string $domain_name, string $document_root = '', bool $enabled = true, string $certificate = '', int $redirect_ssl = -1, int $redirect_www = -1, int $timeout = -1, string $php = '', string $apache_custom_config = '', string $nginx_custom_config = '') {
		$params = ['domain_name' => $domain_name, 'document_root' => $document_root, 'enabled' => $enabled, 'certificate' => $certificate, 'redirect_ssl' => $redirect_ssl, 'redirect_www' => $redirect_www, 'timeout' => $timeout, 'php' => $php, 'apache_custom_config' => $apache_custom_config, 'nginx_custom_config' => $nginx_custom_config];
		return $this->CallMethod('web/apache/domain_edit', $params, true);
	}

	// web/apache/domain_delete
	public function web___apache___domain_delete(string $domain_name, bool $recursive) {
		$params = ['domain_name' => $domain_name, 'recursive' => $recursive];
		return $this->CallMethod('web/apache/domain_delete', $params, true);
	}

	// web/apache/domain_host_list
	public function web___apache___domain_host_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_host_list', $params, true);
	}

	// web/apache/domain_host_add
	public function web___apache___domain_host_add(string $domain_name, string $ip, int $port, bool $ssl) {
		$params = ['domain_name' => $domain_name, 'ip' => $ip, 'port' => $port, 'ssl' => $ssl];
		return $this->CallMethod('web/apache/domain_host_add', $params, true);
	}

	// web/apache/domain_host_delete
	public function web___apache___domain_host_delete(string $domain_name, string $ip, int $port, bool $ssl) {
		$params = ['domain_name' => $domain_name, 'ip' => $ip, 'port' => $port, 'ssl' => $ssl];
		return $this->CallMethod('web/apache/domain_host_delete', $params, true);
	}

	// web/apache/domain_proxy_list
	public function web___apache___domain_proxy_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_proxy_list', $params, true);
	}

	// web/apache/domain_proxy_add
	public function web___apache___domain_proxy_add(string $domain_name, string $from, string $to) {
		$params = ['domain_name' => $domain_name, 'from' => $from, 'to' => $to];
		return $this->CallMethod('web/apache/domain_proxy_add', $params, true);
	}

	// web/apache/domain_proxy_delete
	public function web___apache___domain_proxy_delete(string $domain_name, string $from, string $to) {
		$params = ['domain_name' => $domain_name, 'from' => $from, 'to' => $to];
		return $this->CallMethod('web/apache/domain_proxy_delete', $params, true);
	}

	// web/apache/domain_alias_list
	public function web___apache___domain_alias_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_alias_list', $params, true);
	}

	// web/apache/domain_alias_add
	public function web___apache___domain_alias_add(string $domain_name, string $alias) {
		$params = ['domain_name' => $domain_name, 'alias' => $alias];
		return $this->CallMethod('web/apache/domain_alias_add', $params, true);
	}

	// web/apache/domain_alias_delete
	public function web___apache___domain_alias_delete(string $domain_name, string $alias) {
		$params = ['domain_name' => $domain_name, 'alias' => $alias];
		return $this->CallMethod('web/apache/domain_alias_delete', $params, true);
	}

	// web/apache/domain_cert_add
	public function web___apache___domain_cert_add(string $domain_name, string $cert_name, string $private_key, string $crt, string $csr = '', string $ca = '') {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'private_key' => $private_key, 'crt' => $crt, 'csr' => $csr, 'ca' => $ca];
		return $this->CallMethod('web/apache/domain_cert_add', $params, true);
	}

	// web/apache/domain_cert_edit
	public function web___apache___domain_cert_edit(string $domain_name, string $cert_name, string $private_key, string $crt, string $csr = '', string $ca = '') {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'private_key' => $private_key, 'crt' => $crt, 'csr' => $csr, 'ca' => $ca];
		return $this->CallMethod('web/apache/domain_cert_edit', $params, true);
	}

	// web/apache/domain_cert_get
	public function web___apache___domain_cert_get(string $domain_name, string $cert_name, bool $extended = true) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'extended' => $extended];
		return $this->CallMethod('web/apache/domain_cert_get', $params, true);
	}

	// web/apache/domain_cert_name_list
	public function web___apache___domain_cert_name_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_cert_name_list', $params, true);
	}

	// web/apache/domain_cert_list
	public function web___apache___domain_cert_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/domain_cert_list', $params, true);
	}

	// web/apache/domain_cert_delete
	public function web___apache___domain_cert_delete(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/apache/domain_cert_delete', $params, true);
	}

	// web/apache/domain_cert_show_crt
	public function web___apache___domain_cert_show_crt(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/apache/domain_cert_show_crt', $params, true);
	}

	// web/apache/domain_cert_show_csr
	public function web___apache___domain_cert_show_csr(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/apache/domain_cert_show_csr', $params, true);
	}

	// web/apache/domain_cert_show_key
	public function web___apache___domain_cert_show_key(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/apache/domain_cert_show_key', $params, true);
	}

	// web/apache/domain_cert_show_ca
	public function web___apache___domain_cert_show_ca(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/apache/domain_cert_show_ca', $params, true);
	}

	// web/apache/cert_verify
	public function web___apache___cert_verify(string $private_key, string $csr, string $crt, string $ca = '') {
		$params = ['private_key' => $private_key, 'csr' => $csr, 'crt' => $crt, 'ca' => $ca];
		return $this->CallMethod('web/apache/cert_verify', $params, true);
	}

	// web/apache/cert_generate_selfsigned
	public function web___apache___cert_generate_selfsigned(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/apache/cert_generate_selfsigned', $params, true);
	}

	// web/apache/dns_ovh_read
	public function web___apache___dns_ovh_read() {
		$params = [];
		return $this->CallMethod('web/apache/dns_ovh_read', $params, true);
	}

	// web/apache/import_from
	public function web___apache___import_from(string $host, string $user_name, string $password, bool $include_www) {
		$params = ['host' => $host, 'user_name' => $user_name, 'password' => $password, 'include_www' => $include_www];
		return $this->CallMethod('web/apache/import_from', $params, true);
	}

	// web/letsencrypt/cert_get
	public function web___letsencrypt___cert_get(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/letsencrypt/cert_get', $params, true);
	}

	// web/letsencrypt/cert_request
	public function web___letsencrypt___cert_request(string $domain_name, bool $terms_of_service_agreed, bool $ignore_invalid = false) {
		$params = ['domain_name' => $domain_name, 'terms_of_service_agreed' => $terms_of_service_agreed, 'ignore_invalid' => $ignore_invalid];
		return $this->CallMethod('web/letsencrypt/cert_request', $params, true);
	}

	// web/letsencrypt/account_get
	public function web___letsencrypt___account_get() {
		$params = [];
		return $this->CallMethod('web/letsencrypt/account_get', $params, true);
	}

	// web/letsencrypt/account_update
	public function web___letsencrypt___account_update(string $email) {
		$params = ['email' => $email];
		return $this->CallMethod('web/letsencrypt/account_update', $params, true);
	}

	// web/nginx/domain_edit
	public function web___nginx___domain_edit(string $domain_name, string $document_root = '', bool $enabled = true, string $certificate = '', int $redirect_ssl = -1, int $redirect_www = -1, int $timeout = -1, string $php = '', string $nginx_custom_config = '') {
		$params = ['domain_name' => $domain_name, 'document_root' => $document_root, 'enabled' => $enabled, 'certificate' => $certificate, 'redirect_ssl' => $redirect_ssl, 'redirect_www' => $redirect_www, 'timeout' => $timeout, 'php' => $php, 'nginx_custom_config' => $nginx_custom_config];
		return $this->CallMethod('web/nginx/domain_edit', $params, true);
	}

	// web/nginx/domain_show_log_access
	public function web___nginx___domain_show_log_access(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_show_log_access', $params, true);
	}

	// web/nginx/domain_show_log_error
	public function web___nginx___domain_show_log_error(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_show_log_error', $params, true);
	}

	// web/nginx/domain_show_config
	public function web___nginx___domain_show_config(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_show_config', $params, true);
	}

	// web/nginx/restart
	public function web___nginx___restart() {
		$params = [];
		return $this->CallMethod('web/nginx/restart', $params, true);
	}

	// web/nginx/reload
	public function web___nginx___reload() {
		$params = [];
		return $this->CallMethod('web/nginx/reload', $params, true);
	}

	// web/nginx/dicts
	public function web___nginx___dicts() {
		$params = [];
		return $this->CallMethod('web/nginx/dicts', $params, true);
	}

	// web/nginx/domain_name_list
	public function web___nginx___domain_name_list(string $parent_domain_name = '') {
		$params = ['parent_domain_name' => $parent_domain_name];
		return $this->CallMethod('web/nginx/domain_name_list', $params, true);
	}

	// web/nginx/domain_list
	public function web___nginx___domain_list(bool $root_only = true) {
		$params = ['root_only' => $root_only];
		return $this->CallMethod('web/nginx/domain_list', $params, true);
	}

	// web/nginx/domain_get
	public function web___nginx___domain_get(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_get', $params, true);
	}

	// web/nginx/domain_add
	public function web___nginx___domain_add(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_add', $params, true);
	}

	// web/nginx/domain_restore
	public function web___nginx___domain_restore(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_restore', $params, true);
	}

	// web/nginx/domain_restorable_list
	public function web___nginx___domain_restorable_list() {
		$params = [];
		return $this->CallMethod('web/nginx/domain_restorable_list', $params, true);
	}

	// web/nginx/domain_restorable_purge
	public function web___nginx___domain_restorable_purge() {
		$params = [];
		return $this->CallMethod('web/nginx/domain_restorable_purge', $params, true);
	}

	// web/nginx/domain_edit
	public function web___nginx___domain_edit2(string $domain_name, string $document_root = '', bool $enabled = true, string $certificate = '', int $redirect_ssl = -1, int $redirect_www = -1, int $timeout = -1, string $php = '', string $apache_custom_config = '', string $nginx_custom_config = '') {
		$params = ['domain_name' => $domain_name, 'document_root' => $document_root, 'enabled' => $enabled, 'certificate' => $certificate, 'redirect_ssl' => $redirect_ssl, 'redirect_www' => $redirect_www, 'timeout' => $timeout, 'php' => $php, 'apache_custom_config' => $apache_custom_config, 'nginx_custom_config' => $nginx_custom_config];
		return $this->CallMethod('web/nginx/domain_edit', $params, true);
	}

	// web/nginx/domain_delete
	public function web___nginx___domain_delete(string $domain_name, bool $recursive) {
		$params = ['domain_name' => $domain_name, 'recursive' => $recursive];
		return $this->CallMethod('web/nginx/domain_delete', $params, true);
	}

	// web/nginx/domain_host_list
	public function web___nginx___domain_host_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_host_list', $params, true);
	}

	// web/nginx/domain_host_add
	public function web___nginx___domain_host_add(string $domain_name, string $ip, int $port, bool $ssl) {
		$params = ['domain_name' => $domain_name, 'ip' => $ip, 'port' => $port, 'ssl' => $ssl];
		return $this->CallMethod('web/nginx/domain_host_add', $params, true);
	}

	// web/nginx/domain_host_delete
	public function web___nginx___domain_host_delete(string $domain_name, string $ip, int $port, bool $ssl) {
		$params = ['domain_name' => $domain_name, 'ip' => $ip, 'port' => $port, 'ssl' => $ssl];
		return $this->CallMethod('web/nginx/domain_host_delete', $params, true);
	}

	// web/nginx/domain_proxy_list
	public function web___nginx___domain_proxy_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_proxy_list', $params, true);
	}

	// web/nginx/domain_proxy_add
	public function web___nginx___domain_proxy_add(string $domain_name, string $from, string $to) {
		$params = ['domain_name' => $domain_name, 'from' => $from, 'to' => $to];
		return $this->CallMethod('web/nginx/domain_proxy_add', $params, true);
	}

	// web/nginx/domain_proxy_delete
	public function web___nginx___domain_proxy_delete(string $domain_name, string $from, string $to) {
		$params = ['domain_name' => $domain_name, 'from' => $from, 'to' => $to];
		return $this->CallMethod('web/nginx/domain_proxy_delete', $params, true);
	}

	// web/nginx/domain_alias_list
	public function web___nginx___domain_alias_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_alias_list', $params, true);
	}

	// web/nginx/domain_alias_add
	public function web___nginx___domain_alias_add(string $domain_name, string $alias) {
		$params = ['domain_name' => $domain_name, 'alias' => $alias];
		return $this->CallMethod('web/nginx/domain_alias_add', $params, true);
	}

	// web/nginx/domain_alias_delete
	public function web___nginx___domain_alias_delete(string $domain_name, string $alias) {
		$params = ['domain_name' => $domain_name, 'alias' => $alias];
		return $this->CallMethod('web/nginx/domain_alias_delete', $params, true);
	}

	// web/nginx/domain_cert_add
	public function web___nginx___domain_cert_add(string $domain_name, string $cert_name, string $private_key, string $crt, string $csr = '', string $ca = '') {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'private_key' => $private_key, 'crt' => $crt, 'csr' => $csr, 'ca' => $ca];
		return $this->CallMethod('web/nginx/domain_cert_add', $params, true);
	}

	// web/nginx/domain_cert_edit
	public function web___nginx___domain_cert_edit(string $domain_name, string $cert_name, string $private_key, string $crt, string $csr = '', string $ca = '') {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'private_key' => $private_key, 'crt' => $crt, 'csr' => $csr, 'ca' => $ca];
		return $this->CallMethod('web/nginx/domain_cert_edit', $params, true);
	}

	// web/nginx/domain_cert_get
	public function web___nginx___domain_cert_get(string $domain_name, string $cert_name, bool $extended = true) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name, 'extended' => $extended];
		return $this->CallMethod('web/nginx/domain_cert_get', $params, true);
	}

	// web/nginx/domain_cert_name_list
	public function web___nginx___domain_cert_name_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_cert_name_list', $params, true);
	}

	// web/nginx/domain_cert_list
	public function web___nginx___domain_cert_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/domain_cert_list', $params, true);
	}

	// web/nginx/domain_cert_delete
	public function web___nginx___domain_cert_delete(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/nginx/domain_cert_delete', $params, true);
	}

	// web/nginx/domain_cert_show_crt
	public function web___nginx___domain_cert_show_crt(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/nginx/domain_cert_show_crt', $params, true);
	}

	// web/nginx/domain_cert_show_csr
	public function web___nginx___domain_cert_show_csr(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/nginx/domain_cert_show_csr', $params, true);
	}

	// web/nginx/domain_cert_show_key
	public function web___nginx___domain_cert_show_key(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/nginx/domain_cert_show_key', $params, true);
	}

	// web/nginx/domain_cert_show_ca
	public function web___nginx___domain_cert_show_ca(string $domain_name, string $cert_name) {
		$params = ['domain_name' => $domain_name, 'cert_name' => $cert_name];
		return $this->CallMethod('web/nginx/domain_cert_show_ca', $params, true);
	}

	// web/nginx/cert_verify
	public function web___nginx___cert_verify(string $private_key, string $csr, string $crt, string $ca = '') {
		$params = ['private_key' => $private_key, 'csr' => $csr, 'crt' => $crt, 'ca' => $ca];
		return $this->CallMethod('web/nginx/cert_verify', $params, true);
	}

	// web/nginx/cert_generate_selfsigned
	public function web___nginx___cert_generate_selfsigned(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/nginx/cert_generate_selfsigned', $params, true);
	}

	// web/nginx/dns_ovh_read
	public function web___nginx___dns_ovh_read() {
		$params = [];
		return $this->CallMethod('web/nginx/dns_ovh_read', $params, true);
	}

	// web/nginx/import_from
	public function web___nginx___import_from(string $host, string $user_name, string $password, bool $include_www) {
		$params = ['host' => $host, 'user_name' => $user_name, 'password' => $password, 'include_www' => $include_www];
		return $this->CallMethod('web/nginx/import_from', $params, true);
	}

	// web/php/dicts
	public function web___php___dicts() {
		$params = [];
		return $this->CallMethod('web/php/dicts', $params, true);
	}

	// web/php/module_list
	public function web___php___module_list() {
		$params = [];
		return $this->CallMethod('web/php/module_list', $params, true);
	}

	// web/php/module_install
	public function web___php___module_install(string $mod_name) {
		$params = ['mod_name' => $mod_name];
		return $this->CallMethod('web/php/module_install', $params, true);
	}

	// web/php/module_uninstall
	public function web___php___module_uninstall(string $mod_name) {
		$params = ['mod_name' => $mod_name];
		return $this->CallMethod('web/php/module_uninstall', $params, true);
	}

	// web/php/variable_list
	public function web___php___variable_list(string $domain_name) {
		$params = ['domain_name' => $domain_name];
		return $this->CallMethod('web/php/variable_list', $params, true);
	}

	// web/php/variable_edit
	public function web___php___variable_edit(string $domain_name, array $variables) {
		$params = ['domain_name' => $domain_name, 'variables' => $variables];
		return $this->CallMethod('web/php/variable_edit', $params, true);
	}

	// web/php/restart
	public function web___php___restart() {
		$params = [];
		return $this->CallMethod('web/php/restart', $params, true);
	}

	// web/php/reload
	public function web___php___reload() {
		$params = [];
		return $this->CallMethod('web/php/reload', $params, true);
	}

	// web/webapps/webapp_list
	public function web___webapps___webapp_list() {
		$params = [];
		return $this->CallMethod('web/webapps/webapp_list', $params, true);
	}

	// web/webapps/webapp_get
	public function web___webapps___webapp_get(string $app_name) {
		$params = ['app_name' => $app_name];
		return $this->CallMethod('web/webapps/webapp_get', $params, true);
	}

	// web/webapps/webapp_install
	public function web___webapps___webapp_install(string $app_name, string $domain_name, string $sub_dir = '', string $db_name = '', string $db_user = '', string $db_password = '', array $variables = []) {
		$params = ['app_name' => $app_name, 'domain_name' => $domain_name, 'sub_dir' => $sub_dir, 'db_name' => $db_name, 'db_user' => $db_user, 'db_password' => $db_password, 'variables' => $variables];
		return $this->CallMethod('web/webapps/webapp_install', $params, true);
	}

	// web/webapps/webapp_dir_validate
	public function web___webapps___webapp_dir_validate(string $domain_name, string $sub_dir = '') {
		$params = ['domain_name' => $domain_name, 'sub_dir' => $sub_dir];
		return $this->CallMethod('web/webapps/webapp_dir_validate', $params, true);
	}


    //endregion
}

/*
//region Example

$tcp = new TinyCPConnector('127.0.0.1', 63886);
if($tcp->Auth('login', 'password'))
{
    $domains = $tcp->web___apache___domain_list();
    $config = $tcp->web___apache___domain_show_config($domains[0]['domain_name']);
    print_r($config);
}

*/
