<?php
/**
 * Pickles 2
 */
namespace picklesFramework2;

/**
 * Pickles 2 core class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class px{
	/**
	 * Pickles 2 のホームディレクトリのパス
	 * @access private
	 */
	private $realpath_homedir;

	/**
	 * コンテンツディレクトリのパス
	 * @access private
	 */
	private $path_content;

	/**
	 * オブジェクト
	 * @access private
	 */
	private $conf, $fs, $req, $site, $bowl, $pxcmd;

	/**
	 * ディレクトリインデックス(省略できるファイル名の一覧)
	 * @access private
	 */
	private $directory_index;

	/**
	 * プロセス関連情報
	 * @access private
	 */
	private $proc_type, $proc_id, $proc_num = 0;

	/**
	 * HTTP Response Header List
	 */
	private $response_headers = array(
		"Content-type" => array("text/html")
	);

	/**
	 * 関連ファイルのURL情報
	 * @access private
	 */
	private $relatedlinks = array();

	/**
	 * エラーメッセージ情報
	 */
	private $errors = array();

	/**
	 * 応答ステータスコード
	 * @access private
	 */
	private $response_status = 200;

	/**
	 * 応答ステータスメッセージ
	 * @access private
	 */
	private $response_message = 'OK';

	/**
	 * Pickles Framework のバージョン番号を取得する。
	 *
	 * Pickles Framework のバージョン番号はこのメソッドにハードコーディングされます。
	 *
	 * バージョン番号発行の規則は、 Semantic Versioning 2.0.0 仕様に従います。
	 * - [Semantic Versioning(英語原文)](http://semver.org/)
	 * - [セマンティック バージョニング(日本語)](http://semver.org/lang/ja/)
	 *
	 * *[ナイトリービルド]*<br />
	 * バージョン番号が振られていない、開発途中のリビジョンを、ナイトリービルドと呼びます。<br />
	 * ナイトリービルドの場合、バージョン番号は、次のリリースが予定されているバージョン番号に、
	 * ビルドメタデータ `+nb` を付加します。
	 * 通常は、プレリリース記号 `alpha` または `beta` を伴うようにします。
	 * - 例：1.0.0-beta.12+nb (=1.0.0-beta.12リリース前のナイトリービルド)
	 *
	 * @return string バージョン番号を示す文字列
	 */
	public function get_version(){
		return '2.0.29';
	}

	/**
	 * Constructor
	 *
	 * @param string $path_homedir Pickles のホームディレクトリのパス
	 */
	public function __construct( $path_homedir ){
		// initialize PHP
		if( !extension_loaded( 'mbstring' ) ){
			trigger_error('mbstring not loaded.');
		}
		if( is_callable('mb_internal_encoding') ){
			mb_internal_encoding('UTF-8');
			@ini_set( 'mbstring.internal_encoding' , 'UTF-8' );
			@ini_set( 'mbstring.http_input' , 'UTF-8' );
			@ini_set( 'mbstring.http_output' , 'UTF-8' );
		}
		@ini_set( 'default_charset' , 'UTF-8' );
		if( is_callable('mb_detect_order') ){
			@ini_set( 'mbstring.detect_order' , 'UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP,JIS,ASCII' );
			mb_detect_order( 'UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP,JIS,ASCII' );
		}
		@header_remove('X-Powered-By');
		$this->set_status(200);// 200 OK

		if( !array_key_exists( 'REMOTE_ADDR' , $_SERVER ) ){
			// commandline only
			if( realpath($_SERVER['SCRIPT_FILENAME']) === false ||
				dirname(realpath($_SERVER['SCRIPT_FILENAME'])) !== realpath('./')
			){
				if( array_key_exists( 'PWD' , $_SERVER ) && is_file($_SERVER['PWD'].'/'.$_SERVER['SCRIPT_FILENAME']) ){
					$_SERVER['SCRIPT_FILENAME'] = realpath($_SERVER['PWD'].'/'.$_SERVER['SCRIPT_FILENAME']);
				}else{
					// for Windows
					// .px_execute.php で chdir(__DIR__) されていることが前提。
					$_SERVER['SCRIPT_FILENAME'] = realpath('./'.basename($_SERVER['SCRIPT_FILENAME']));
				}
			}
		}

		// load Config
		$this->realpath_homedir = $path_homedir;
		if( is_file($this->realpath_homedir.DIRECTORY_SEPARATOR.'config.json') ){
			$this->conf = json_decode( file_get_contents( $this->realpath_homedir.DIRECTORY_SEPARATOR.'config.json' ) );
		}elseif( is_file($this->realpath_homedir.DIRECTORY_SEPARATOR.'config.php') ){
			$this->conf = include( $this->realpath_homedir.DIRECTORY_SEPARATOR.'config.php' );
		}
		$this->conf = json_decode( json_encode( $this->conf ) );
		if( !@is_array($this->conf->paths_enable_sitemap) ){
			// sitemap のロードを有効にするべきパス。
			$this->conf->paths_enable_sitemap = array(
				'*.html',
				'*.htm',
			);
		}

		if ( @strlen($this->conf->default_timezone) ) {
			@date_default_timezone_set($this->conf->default_timezone);
		}
		if ( !@strlen($this->conf->path_files) ) {
			$this->conf->path_files = '{$dirname}/{$filename}_files/';
		}
		if ( !@strlen($this->conf->copyright) ) {
			// NOTE: 2016-03-15 $conf->copyright 追加
			// 古い環境で Notice レベルのエラーが出る可能性があることを考慮し、初期化する。
			$this->conf->copyright = null;
		}

		// make instance $bowl
		$this->bowl = new bowl();

		// Apply command-line option "--command-php"
		$req = new \tomk79\request();
		if( strlen(@$req->get_cli_option( '--command-php' )) ){
			@$this->conf->commands->php = $req->get_cli_option( '--command-php' );
		}
		if( strlen(@$req->get_cli_option( '-c' )) ){
			@$this->conf->path_phpini = $req->get_cli_option( '-c' );
		}

		// make instance $fs
		$conf = new \stdClass;
		$conf->file_default_permission = $this->conf->file_default_permission;
		$conf->dir_default_permission  = $this->conf->dir_default_permission;
		$conf->filesystem_encoding	 = $this->conf->filesystem_encoding;
		$this->fs = new \tomk79\filesystem( $conf );
		$this->realpath_homedir = $this->fs->get_realpath($this->realpath_homedir.'/');

		// make instance $req
		$conf = new \stdClass;
		$conf->session_name   = $this->conf->session_name;
		$conf->session_expire = $this->conf->session_expire;
		$conf->directory_index_primary = $this->get_directory_index_primary();
		$this->req = new \tomk79\request( $conf );
		if( strlen(@$this->req->get_cli_option( '-u' )) ){
			$_SERVER['HTTP_USER_AGENT'] = @$this->req->get_cli_option( '-u' );
		}

		// 公開キャッシュフォルダが存在しない場合、作成する
		if( strlen(@$this->conf->public_cache_dir) && !@is_dir( './'.$this->conf->public_cache_dir ) ){
			$this->fs->mkdir( './'.$this->conf->public_cache_dir );
		}
		// _sysフォルダが存在しない場合、作成する
		foreach( array(
			'/_sys/',
			'/_sys/ram/',
			'/_sys/ram/applock/',
			'/_sys/ram/caches/',
			'/_sys/ram/data/',
			'/_sys/ram/publish/'
		) as $tmp_path_sys_dir ){
			if( strlen($this->realpath_homedir.$tmp_path_sys_dir) && !@is_dir( $this->realpath_homedir.$tmp_path_sys_dir ) ){
				$this->fs->mkdir( $this->realpath_homedir.$tmp_path_sys_dir );
			}
		}

		// 環境変数 `$_SERVER['DOCUMENT_ROOT']` をセット
		// `$px->get_realpath_docroot()` は、`$conf`, `$fs` を参照するので、
		// これらの初期化の後が望ましい。
		if( !array_key_exists( 'DOCUMENT_ROOT' , $_SERVER ) || !strlen( @$_SERVER['DOCUMENT_ROOT'] ) ){
			// commandline only
			$_SERVER['DOCUMENT_ROOT'] = $this->get_realpath_docroot();
			$_SERVER['DOCUMENT_ROOT'] = realpath( $_SERVER['DOCUMENT_ROOT'] );
		}

		// デフォルトの Content-type を出力
		@$this->header('Content-type: '.$this->get_default_mime_type());


		// ignore されたコンテンツをレスポンスする
		$fnc_response_ignore = function($px){
			$px->set_status(403);// 403 Forbidden
			print 'ignored path';
			exit;
		};
		// pass 判定されたコンテンツをレスポンスする
		$fnc_response_pass = function($px){
			if( !$px->fs()->is_file( './'.$px->req()->get_request_file_path() ) ){
				@$px->header('Content-type: text/html');
				$px->set_status(404);// 404 NotFound
				$px->bowl()->send('<p>404 - File not found.</p>');
				return true;
			}
			$src = $px->fs()->read_file( './'.$px->req()->get_request_file_path() );
			$px->bowl()->send($src);
			return true;
		};

		$pxcmd = $this->get_px_command();
		if( is_null($pxcmd) || !count($pxcmd) ){
			// PXコマンドが無効かつ ignore か pass の場合、
			// この時点でレスポンスを返す。
			if( $this->is_ignore_path( $this->req()->get_request_file_path() ) ){
				// ignore のレスポンス
				$fnc_response_ignore($this);
				return;
			}
			if( $this->get_path_proc_type( $this->req()->get_request_file_path() ) === 'pass' ){
				// pass のレスポンス
				$fnc_response_pass($this);
				return;
			}
		}


		// make instance $pxcmd
		$this->pxcmd = new pxcmd($this);


		// funcs: Before sitemap
		$this->fnc_call_plugin_funcs( @$this->conf->funcs->before_sitemap, $this );


		// make instance $site
		$is_enable_sitemap = $this->is_path_enable_sitemap( $this->req()->get_request_file_path() );
		if( is_object( $this->site ) ){
			// 既にセットされている場合はそのまま利用する
		}elseif( $is_enable_sitemap ){
			$this->site = new site($this);
		}else{
			$this->site = false;
		}



		// execute Content
		$this->path_content = $this->req()->get_request_file_path();
		$ext = $this->get_path_proc_type( $this->req()->get_request_file_path() );
		if($ext !== 'direct' && $ext !== 'pass'){
			if( $is_enable_sitemap ){
				$current_page_info = $this->site()->get_page_info( $this->req()->get_request_file_path() );
				$this->path_content = $current_page_info['content'];
				if( is_null( $this->path_content ) ){
					$this->path_content = $this->req()->get_request_file_path();
				}
				unset($current_page_info);
			}

		}

		foreach( array_keys( get_object_vars( $this->conf->funcs->processor ) ) as $tmp_ext ){
			if( $this->fs()->is_file( './'.$this->path_content.'.'.$tmp_ext ) ){
				$ext = $tmp_ext;
				$this->path_content = $this->path_content.'.'.$tmp_ext;
				break;
			}
		}

		$this->proc_type = $ext;
		unset($ext);


		// funcs: Before contents
		$this->fnc_call_plugin_funcs( @$this->conf->funcs->before_content, $this );


		// PXコマンドが有効、かつ ignore か pass の場合、
		// この時点でレスポンスを返す。
		// ここまでの before sitemap, before contents でレスポンスされた場合は、ここは通らない。
		if( $this->is_ignore_path( $this->req()->get_request_file_path() ) ){
			// ignore のレスポンス
			$fnc_response_ignore($this);
			return;
		}
		if( $this->get_path_proc_type( $this->req()->get_request_file_path() ) === 'pass' ){
			// pass のレスポンス
			$fnc_response_pass($this);
			return;
		}


		// execute content
		self::exec_content( $this );


		// funcs: process functions
		$this->fnc_call_plugin_funcs( @$this->conf->funcs->processor->{$this->proc_type}, $this );



		// funcs: Before output
		$this->fnc_call_plugin_funcs( @$this->conf->funcs->before_output, $this );

	}

	/**
	 * Destructor
	 * @return null
	 */
	public function __destruct(){
		if(is_callable(array($this->site(),'__destruct'))){
			$this->site()->__destruct();
		}
	}

	/**
	 * call plugin functions
	 *
	 * @param mixed $func_list List of plugins function
	 * @return bool 成功時 `true`、失敗時 `false`
	 */
	private function fnc_call_plugin_funcs( $func_list ){
		if( is_null($func_list) ){ return false; }
		$param_arr = func_get_args();
		array_shift($param_arr);

		if( @!empty( $func_list ) ){
			// functions
			if( is_object($func_list) ){
				$func_list = get_object_vars( $func_list );
			}
			if( is_string($func_list) ){
				$fnc_name = $func_list;
				$fnc_name = preg_replace( '/^\\\\*/', '\\', $fnc_name );
				preg_match( '/^(.*?)(?:\\((.*)\\))?$/', $fnc_name, $matched );
				$fnc_name = @$matched[1];
				$option_value = @$matched[2];
				unset($matched);
				if( strlen( $option_value ) ){
					$option_value = json_decode( $option_value );
				}
				// var_dump($fnc_name);
				// var_dump($option_value);
				$this->proc_num ++;
				if( @!strlen($this->proc_id) ){
					$this->proc_id = $this->proc_type.'_'.$this->proc_num;
				}
				array_push( $param_arr, $option_value );
				call_user_func_array( $fnc_name, $param_arr);
				$this->proc_id = null;

			}elseif( is_array($func_list) ){
				foreach( $func_list as $fnc_id=>$fnc_name ){
					if( is_string($fnc_name) ){
						$fnc_name = preg_replace( '/^\\\\*/', '\\', $fnc_name );
					}
					if( is_string($fnc_id) && !preg_match('/^[0-9]+$/', $fnc_id) ){
						$this->proc_id = $fnc_id;
					}
					$param_arr_dd = $param_arr;
					array_unshift( $param_arr_dd, $fnc_name );
					call_user_func_array( array( $this, 'fnc_call_plugin_funcs' ), $param_arr_dd);
				}
			}
			unset($fnc_name);
		}
		return true;
	}


	/**
	 * `$fs` オブジェクトを取得する。
	 *
	 * `$fs`(class [tomk79\filesystem](tomk79.filesystem.html))のインスタンスを返します。
	 *
	 * @see https://github.com/tomk79/filesystem
	 * @return object $fs オブジェクト
	 */
	public function fs(){
		return $this->fs;
	}

	/**
	 * `$req` オブジェクトを取得する。
	 *
	 * `$req`(class [tomk79\request](tomk79.request.html))のインスタンスを返します。
	 *
	 * @see https://github.com/tomk79/request
	 * @return object $req オブジェクト
	 */
	public function req(){
		return $this->req;
	}

	/**
	 * `$site` オブジェクトを取得する。
	 *
	 * `$site`(class [picklesFramework2\site](picklesFramework2.site.html))のインスタンスを返します。
	 *
	 * `$site` は、主にサイトマップCSVから読み込んだページの一覧情報を管理するオブジェクトです。
	 *
	 * `$conf->paths_enable_sitemap` の設定によって、 `$site` がロードされない場合があります。
	 * ロードされない場合は、 `false` が返されます。
	 *
	 * また、サイトマップをロードする前段階(`before_sitemap`) では、 `null` を返します。
	 *
	 * @return object|bool `$site` オブジェクト。ロード前は `null`、ロードされない場合は `false`。
	 */
	public function site(){
		return $this->site;
	}

	/**
	 * `$site` オブジェクトをセットする
	 *
	 * 外部から `$site`(class [picklesFramework2\site](picklesFramework2.site.html))のインスタンスを受け取ります。
	 *
	 * このメソッドを通じて、プラグインから `$site` の振る舞いを変更することができます。
	 *
	 * @param object $site `$site` オブジェクト
	 * @return bool 成功時 `true`、失敗時 `false` を返します。
	 */
	public function set_site( $site ){
		if( !is_object($site) ){
			return false;
		}
		$this->site = $site;
		return true;
	}

	/**
	 * `$pxcmd` オブジェクトを取得する。
	 *
	 * @return object $pxcmd オブジェクト
	 */
	public function pxcmd(){
		return $this->pxcmd;
	}

	/**
	 * `$bowl` オブジェクトを取得する。
	 *
	 * `$bowl`(class [picklesFramework2\bowl](picklesFramework2.bowl.html))のインスタンスを返します。
	 *
	 * @return object $bowl オブジェクト
	 */
	public function bowl(){
		return $this->bowl;
	}

	/**
	 * `$conf` オブジェクトを取得する。
	 * @return object $conf オブジェクト
	 */
	public function conf(){
		return $this->conf;
	}

	/**
	 * ホームディレクトリの絶対パスを取得する (deprecated)
	 * このメソッドの使用は推奨されません。
	 * 代わりに `$px->get_realpath_homedir()` を使用してください。
	 * @return string ホームディレクトリの絶対パス
	 */
	public function get_path_homedir(){
		return $this->get_realpath_homedir();
	}

	/**
	 * ホームディレクトリの絶対パスを取得する。
	 *
	 * ホームディレクトリは、 Pickles 2 のフレームワークが使用するファイルが置かれるディレクトリで、
	 * 主には `config.php`, `sitemaps/*`, `_sys/ram/*` などが格納されているディレクトリのことです。
	 *
	 * pickles2/px-fw-2.x@2.0.29 で `get_path_homedir()` からの名称変更として追加されました。
	 *
	 * @return string ホームディレクトリの絶対パス
	 */
	public function get_realpath_homedir(){
		return $this->realpath_homedir;
	}

	/**
	 * コンテンツのパスを取得する。
	 * @return string コンテンツのパス
	 */
	public function get_path_content(){
		return $this->path_content;
	}

	/**
	 * `directory_index` (省略できるファイル名) の一覧を得る。
	 *
	 * @return array ディレクトリインデックスの一覧
	 */
	public function get_directory_index(){
		if( count($this->directory_index) ){
			return $this->directory_index;
		}
		$tmp_di = $this->conf->directory_index;
		$this->directory_index = array();
		foreach( $tmp_di as $file_name ){
			$file_name = trim($file_name);
			if( !strlen($file_name) ){ continue; }
			array_push( $this->directory_index, $file_name );
		}
		if( !count( $this->directory_index ) ){
			array_push( $this->directory_index, 'index.html' );
		}
		return $this->directory_index;
	}//get_directory_index()

	/**
	 * `directory_index` のいずれかにマッチするための pregパターン式を得る。
	 *
	 * @param string $delimiter pregパターンのデリミタ。省略時は `/` (`preg_quote()` の実装に従う)。
	 * @return string pregパターン
	 */
	public function get_directory_index_preg_pattern( $delimiter = null ){
		$directory_index = $this->get_directory_index();
		foreach( $directory_index as $key=>$row ){
			$directory_index[$key] = preg_quote($row, $delimiter);
		}
		$rtn = '(?:'.implode( '|', $directory_index ).')';
		return $rtn;
	}//get_directory_index_preg_pattern()

	/**
	 * 最も優先されるインデックスファイル名を得る。
	 *
	 * @return string 最も優先されるインデックスファイル名
	 */
	public function get_directory_index_primary(){
		$directory_index = $this->get_directory_index();
		return $directory_index[0];
	}//get_directory_index_primary()

	/**
	 * ファイルの処理方法を調べる。
	 *
	 * 設定値 `$conf->paths_proc_type` の連想配列にもとづいてパスを評価し、
	 * 処理方法の種類を調べて返します。
	 *
	 * @param string $path パス
	 * @return string 処理方法
	 * - ignore = 対象外パス。Pickles 2 のアクセス可能範囲から除外します。このパスにへのアクセスは拒絶され、パブリッシュの対象からも外されます。
	 * - direct = 物理ファイルを、ファイルとして読み込んだでから加工処理を通します。 (direct以外の通常の処理は、PHPファイルとして `include()` されます)
	 * - pass = 物理ファイルを、そのまま無加工で出力します。 (デフォルト)
	 * - その他 = extension名
	 */
	public function get_path_proc_type( $path = null ){
		static $rtn = array();
		static $rtn_after_sitemap = array();
		if( is_null($path) ){
			$path = $this->req()->get_request_file_path();
		}
		$path = $this->fs()->get_realpath( '/'.$path );
		if( is_dir('./'.$path) ){
			$path .= '/'.$this->get_directory_index_primary();
		}
		if( preg_match('/(?:\/|\\\\)$/', $path) ){
			$path .= $this->get_directory_index_primary();
		}
		$path = $this->fs()->normalize_path($path);

		if( is_object( $this->site ) ){
			if( @!is_null($rtn_after_sitemap[$path]) ){
				return $rtn_after_sitemap[$path];
			}
			$page_info = $this->site()->get_page_info( $path );
			if( @strlen( $page_info['proc_type'] ) ){
				$rtn_after_sitemap[$path] = $page_info['proc_type'];
				return $rtn_after_sitemap[$path];
			}
		}

		if( @!is_null($rtn[$path]) ){
			return $rtn[$path];
		}

		$realpath_controot = $this->fs()->normalize_path( $this->fs()->get_realpath( $this->get_path_docroot().$this->get_path_controot() ) );
		foreach( $this->conf->paths_proc_type as $row => $type ){
			if(!is_string($row)){continue;}
			$preg_pattern = preg_quote($this->fs()->normalize_path($this->fs()->get_realpath($row)), '/');
			if( preg_match('/\*/',$preg_pattern) ){
				// ワイルドカードが使用されている場合
				$preg_pattern = preg_quote($row,'/');
				$preg_pattern = preg_replace('/'.preg_quote('\*','/').'/','(?:.*?)',$preg_pattern);//ワイルドカードをパターンに反映
				$preg_pattern = $preg_pattern.'$';//前方・後方一致
			}elseif(is_dir($realpath_controot.$row)){
				$preg_pattern = preg_quote($this->fs()->normalize_path($this->fs()->get_realpath($row)).'/','/');
			}elseif(is_file($realpath_controot.$row)){
				$preg_pattern = preg_quote($this->fs()->normalize_path($this->fs()->get_realpath($row)),'/');
			}
			if( preg_match( '/^'.$preg_pattern.'$/s' , $path ) ){
				$rtn[$path] = $type;
				return $rtn[$path];
			}
		}
		$rtn[$path] = 'pass';// <- default
		return $rtn[$path];
	}//get_path_proc_type();

	/**
	 * サイトマップのロードが有効なパスか調べる。
	 *
	 * 設定値 `$conf->paths_enable_sitemap` の配列にもとづいてパスを評価し、
	 * サイトマップをロードするべきかどうかを判定します。
	 *
	 * @param string $path パス
	 * @return bool 有効の場合 true, 無効の場合 false.
	 */
	public function is_path_enable_sitemap( $path = null ){
		static $rtn = array();
		if( is_null($path) ){
			$path = $this->req()->get_request_file_path();
		}
		$path = $this->fs()->get_realpath( '/'.$path );
		if( is_dir('./'.$path) ){
			$path .= '/'.$this->get_directory_index_primary();
		}
		if( preg_match('/(?:\/|\\\\)$/', $path) ){
			$path .= $this->get_directory_index_primary();
		}
		$path = $this->fs()->normalize_path($path);

		if( @is_bool($rtn[$path]) ){
			return $rtn[$path];
		}

		foreach( $this->conf->paths_enable_sitemap as $row ){
			if(!is_string($row)){continue;}
			$preg_pattern = preg_quote($this->fs()->normalize_path($this->fs()->get_realpath($row)), '/');
			if( preg_match('/\*/',$preg_pattern) ){
				// ワイルドカードが使用されている場合
				$preg_pattern = preg_quote($row,'/');
				$preg_pattern = preg_replace('/'.preg_quote('\*','/').'/','(?:.*?)',$preg_pattern);//ワイルドカードをパターンに反映
				$preg_pattern = $preg_pattern.'$';//前方・後方一致
			}elseif(is_dir($row)){
				$preg_pattern = preg_quote($this->fs()->normalize_path($this->fs()->get_realpath($row)).'/','/');
			}elseif(is_file($row)){
				$preg_pattern = preg_quote($this->fs()->normalize_path($this->fs()->get_realpath($row)),'/');
			}
			if( preg_match( '/^'.$preg_pattern.'$/s' , $path ) ){
				$rtn[$path] = true;
				return $rtn[$path];
			}
		}
		$rtn[$path] = false;// <- default
		return $rtn[$path];
	}//is_path_enable_sitemap();

	/**
	 * 除外ファイルか調べる。
	 *
	 * @param string $path パス
	 * @return bool 除外ファイルの場合 `true`、それ以外の場合に `false` を返します。
	 */
	public function is_ignore_path( $path ){
		$type = $this->get_path_proc_type($path);
		if( $type == 'ignore' ){
			return true;
		}
		return false;
	}//is_ignore_path();

	/**
	 * デフォルトの MIME Type を取得する。
	 *
	 * @param string $path パス
	 * @return string MIME Type
	 */
	private function get_default_mime_type( $path = null ){
		$rtn = null;
		if(@!strlen($path)){
			$path = $this->req()->get_request_file_path();
		}
		$extension = $this->get_path_proc_type( $path );
		switch( strtolower( $extension ) ){
			case 'pass':
			case 'ignore':
			case 'direct':
				$extension = pathinfo( $path , PATHINFO_EXTENSION );
				break;
			default:
				break;
		}

		switch( strtolower( $extension ) ){
			case 'css':
				$rtn = 'text/css';
				break;
			case 'js':
				$rtn = 'text/javascript';
				break;
			case 'html':
			case 'htm':
			case 'shtml':
			case 'shtm':
			case 'xbm':
				$rtn = 'text/html';
				break;
			case 'png': $rtn = 'image/png'; break;
			case 'gif': $rtn = 'image/gif'; break;
			case 'jpg':case 'jpeg':case 'jpe': $rtn = 'image/jpeg'; break;
			case 'svg':case 'svgz': $rtn = 'image/svg+xml'; break;
			case 'csv': $rtn = 'text/comma-separated-values'; break;
			case 'tsv': $rtn = 'text/tab-separated-values'; break;
			case 'pdf': $rtn = 'application/pdf'; break;
			case 'swf': $rtn = 'application/x-shockwave-flash'; break;
			case 'txt':case 'text':
			default:
				$rtn = 'text/plain'; break;
		}
		return $rtn;
	} // get_default_mime_type()

	/**
	 * response status code を取得する。
	 *
	 * `$px->set_status()` で登録した情報を取り出します。
	 *
	 * @return int ステータスコード (100〜599の間の数値)
	 */
	public function get_status(){
		return $this->response_status;
	}
	/**
	 * response ステータスメッセージを取得する。
	 *
	 * `$px->set_status()` で登録した情報を取り出します。
	 *
	 * @return string ステータスメッセージ
	 */
	public function get_status_message(){
		return $this->response_message;
	}

	/**
	 * response status code をセットする
	 * @param int $code ステータスコード (100〜599の間の数値)
	 * @param string $message ステータスメッセージ
	 * @return bool 成功時 `true`, 失敗時 `false`
	 */
	public function set_status($code, $message = null){
		$code = intval($code);
		if( strlen( $code ) != 3 ){
			return false;
		}
		if( !($code >= 100 && $code <= 599) ){
			return false;
		}
		$this->response_status = intval($code);
		if( !strlen($message) ){
			switch( $this->response_status ){
				case 200: $message = 'OK'; break;
				case 403: $message = 'Forbidden'; break;
				case 404: $message = 'NotFound'; break;
				default:break;
			}
		}
		$this->response_message = $message;

		@header('HTTP/1.1 '.$this->response_status.' '.$this->response_message);
		return true;
	}

	/**
	 * エラーメッセージを送信する。
	 *
	 * @param $message string エラーメッセージ
	 * @return bool true
	 */
	public function error( $message = null ){
		@array_push( $this->errors , $message );
		@header('X-PXFW-ERROR: '.$message.'', false);
		return true;
	}

	/**
	 * 送信されたエラーメッセージの一覧を取得する。
	 *
	 * @return array 送信済みのエラーメッセージの一覧
	 */
	public function get_errors(){
		return $this->errors;
	}

	/**
	 * 拡張ヘッダ `X-PXFW-RELATEDLINK` にリンクを追加する。
	 *
	 * 拡張ヘッダ `X-PXFW-RELATEDLINK` は、サイトマップや物理ディレクトリから発見できないファイルを、Pickles Framework のパブリッシュツールに知らせます。
	 *
	 * 通常、Pickles Framework のパブリッシュツールは 動的に生成されたページなどを知ることができず、パブリッシュされません。このメソッドを通じて、明示的に知らせる必要があります。
	 *
	 * @param string $path リンクのパス
	 * @return bool 正常時 `true`、失敗した場合 `false`
	 */
	public function add_relatedlink( $path ){
		$path = trim($path);
		if(!strlen($path)){
			return false;
		}
		array_push( $this->relatedlinks , $path );
		@header('X-PXFW-RELATEDLINK: '.$path.'', false);

		return true;
	}

	/**
	 * 拡張ヘッダ `X-PXFW-RELATEDLINK` に追加されたリンクを取得する。
	 *
	 * 拡張ヘッダ `X-PXFW-RELATEDLINK` に、既に追加されているリンクの一覧を取得します。
	 *
	 * リンクは、 `add_relatedlink()` を通じて追加されます。
	 *
	 * @return array 正常時 `true`、失敗した場合 `false`
	 */
	public function get_relatedlinks(){
		return $this->relatedlinks;
	}

	/**
	 * PX Command を取得する。
	 * @return array コマンド配列(ドットで区切られた結果の配列)
	 */
	public function get_px_command(){
		if( !$this->conf()->allow_pxcommands && !$this->req()->is_cmd() ){
			return null;
		}
		$rtn = array();
		$cmd = $this->req()->get_param('PX');
		if( is_string($cmd) ){
			$rtn = explode('.', $cmd);
		}
		return $rtn;
	}// get_px_command()

	/**
	 * コンテンツを実行する。
	 * @param object $px picklesオブジェクト
	 * @return bool true
	 */
	private static function exec_content( $px ){
		if( !$px->fs()->is_file( './'.$px->get_path_content() ) ){
			@$px->header('Content-type: text/html');
			$px->set_status(404);// 404 NotFound
			$px->bowl()->send('<p>404 - File not found.</p>');
			return true;
		}
		if( $px->proc_type === 'direct' ){
			$src = $px->fs()->read_file( './'.$px->get_path_content() );
		}else{
			ob_start();
			include( './'.$px->get_path_content() );
			$src = ob_get_clean();
		}
		$px->bowl()->send($src);
		return true;
	}

	/**
	 * Contents Manifesto のソースを取得する。
	 * @return string HTMLコード
	 */
	public function get_contents_manifesto(){
		$px = $this;
		$rtn = '';
		if( !strlen( @$this->conf()->contents_manifesto ) ){
			return '';
		}
		$realpath = $this->get_realpath_docroot().$this->href( @$this->conf()->contents_manifesto );
		if( !$this->fs()->is_file( $realpath ) ){
			return '';
		}
		ob_start();
		include( $realpath );
		$rtn = ob_get_clean();
		return $rtn;
	}

	/**
	 * 実行者がパブリッシュツールかどうか調べる。
	 *
	 * @return bool パブリッシュツールの場合 `true`, それ以外の場合 `false` を返します。
	 */
	public function is_publish_tool(){
		if( !strlen( @$_SERVER['HTTP_USER_AGENT'] ) ){
			// UAが空白なら パブリッシュツール
			return true;
		}
		if( preg_match( '/PicklesCrawler/', @$_SERVER['HTTP_USER_AGENT'] ) ){
			// "PicklesCrawler" が含まれていたら パブリッシュツール
			return true;
		}
		return false;
	}

	/**
	 * リンク先のパスを生成する。
	 *
	 * 引数には、リンク先のパス、またはページIDを受け取り、
	 * 完成されたリンク先情報(aタグのhref属性にそのまま適用できる状態)にして返します。
	 *
	 * Pickles Framework がドキュメントルート直下にインストールされていない場合、インストールパスを追加した値を返します。
	 *
	 * `http://` などスキーマから始まる情報を受け取ることもできます。
	 *
	 * 実装例：
	 * <pre>&lt;?php
	 * // パスを指定する例
	 * print '&lt;a href=&quot;'.htmlspecialchars( $px-&gt;href('/aaa/bbb.html') ).'&quot;&gt;リンク&lt;/a&gt;';
	 *
	 * // ページIDを指定する例
	 * print '&lt;a href=&quot;'.htmlspecialchars( $px-&gt;href('A-00') ).'&quot;&gt;リンク&lt;/a&gt;';
	 * ?&gt;</pre>
	 *
	 * インストールパスがドキュメントルートではない場合の例:
	 * <pre>&lt;?php
	 * // インストールパスが &quot;/installpath/&quot; の場合
	 * print '&lt;a href=&quot;'.htmlspecialchars( $px-&gt;href('/aaa/bbb.html') ).'&quot;&gt;リンク&lt;/a&gt;';
	 *	 // &quot;/installpath/aaa/bbb.html&quot; が返されます。
	 * ?&gt;</pre>
	 *
	 * @param string $linkto リンク先の パス または ページID
	 *
	 * @return string href属性値
	 */
	public function href( $linkto ){
		$parsed_url = parse_url($linkto);
		if( $this->site() !== false ){
			$tmp_page_info_by_id = $this->site()->get_page_info_by_id($linkto);
			if( !$tmp_page_info_by_id ){
				$tmp_page_info_by_id = $this->site()->get_page_info_by_id(@$parsed_url['path']);
			}
		}
		$path = $linkto;
		$path_type = false;
		$path_type = $this->get_path_type( $linkto );
		if( @$tmp_page_info_by_id['id'] == $linkto || @$tmp_page_info_by_id['id'] == @$parsed_url['path'] ){
			$path = @$tmp_page_info_by_id['path'];
			$path_type = $this->get_path_type( $path );
		}
		unset($tmp_page_info_by_id);

		if( preg_match( '/^alias[0-9]*\:(.+)/' , $path , $tmp_matched ) ){
			//  エイリアスを解決
			$path = $tmp_matched[1];
		}elseif( $path_type == 'dynamic' ){
			//  ダイナミックパスをバインド
			// $sitemap_dynamic_path = $this->site()->get_dynamic_path_info( $linkto );
			// $tmp_path = $sitemap_dynamic_path['path_original'];
			$tmp_path = $path;
			$path = '';
			while( 1 ){
				if( !preg_match( '/^(.*?)\{(\$|\*)([a-zA-Z0-9\_\-]*)\}(.*)$/s' , $tmp_path , $tmp_matched ) ){
					$path .= $tmp_path;
					break;
				}
				$path .= $tmp_matched[1];
				if(!strlen($tmp_matched[3])){
					//無名のパラメータはバインドしない。
				}elseif( !is_null( $this->req()->get_path_param($tmp_matched[3]) ) ){
					$path .= $this->req()->get_path_param($tmp_matched[3]);
				}else{
					$path .= $tmp_matched[3];
				}
				$tmp_path = $tmp_matched[4];
				continue;
			}
			unset($tmp_path , $tmp_matched);
		}

		$path = $this->fs()->normalize_path($path);
		$path_type = $this->get_path_type( $path );
		switch( $path_type ){
			case 'full_url':
			case 'javascript':
			case 'anchor':
				break;
			default:
				// index.htmlを省略
				$path = preg_replace('/\/'.$this->get_directory_index_preg_pattern().'((?:\?|\#).*)?$/si','/$1',$path);
				if( preg_match( '/^\\//' , $path ) ){
					//  スラッシュから始まる絶対パスの場合、
					//  インストールパスを起点としたパスに書き変えて返す。
					// $path = preg_replace( '/^\/+/' , '' , $path );
					$path = preg_replace('/\/$/', '', $this->get_path_controot()).$path;
				}
				$parsed_url_fin = parse_url($path);
				$path = $this->fs()->normalize_path( $parsed_url_fin['path'] );

				// パラメータを、引数の生の状態に戻す。
				$path .= (strlen(@$parsed_url['query'])?'?'.@$parsed_url['query']:(strlen(@$parsed_url_fin['query'])?'?'.@$parsed_url_fin['query']:''));
				$path .= (strlen(@$parsed_url['fragment'])?'#'.@$parsed_url['fragment']:(strlen(@$parsed_url_fin['fragment'])?'?'.@$parsed_url_fin['fragment']:''));
				break;
		}

		return $path;
	}//href()

	/**
	 * リンクタグ(aタグ)を生成する。
	 *
	 * リンク先の パス または ページID を受け取り、aタグを生成して返します。リンク先は、`href()` メソッドを通して調整されます。
	 *
	 * このメソッドは、受け取ったパス(またはページID)をもとに、サイトマップからページ情報を取得し、aタグを自動補完します。
	 * 例えば、パンくず情報をもとに `class="current"` を付加したり、ページタイトルをラベルとして採用します。
	 *
	 * この動作は、引数 `$options` に値を指定して変更することができます。
	 *
	 * 実装例:
	 * <pre>&lt;?php
	 * // ページ /aaa/bbb.html へのリンクを生成
	 * print $px-&gt;mk_link('/aaa/bbb.html');
	 *
	 * // ページ /aaa/bbb.html へのリンクをオプション付きで生成
	 * print $px-&gt;mk_link('/aaa/bbb.html', array(
	 *	 'label'=>'<span>リンクラベル</span>', //リンクラベルを文字列で指定
	 *	 'class'=>'class_a class_b', //CSSのクラス名を指定
	 *	 'no_escape'=>true, //エスケープ処理をキャンセル
	 *	 'current'=>true //カレント表示にする
	 * ));
	 * ?&gt;</pre>
	 *
	 * 第2引数に文字列または関数としてリンクラベルを渡す方法もあります。
	 * この場合、その他のオプションは第3引数に渡すことができます。
	 *
	 * 実装例:
	 * <pre>&lt;?php
	 * // ページ /aaa/bbb.html へのリンクをオプション付きで生成
	 * print $px-&gt;mk_link('/aaa/bbb.html',
	 *   '<span>リンクラベル</span>' , //リンクラベルを文字列で指定
	 *   array(
	 *	 'class'=>'class_a class_b',
	 *	 'no_escape'=>true,
	 *	 'current'=>true
	 *   )
	 * );
	 * ?&gt;</pre>
	 *
	 * リンクのラベルはコールバック関数でも指定できます。
	 * コールバック関数には、リンク先のページ情報を格納した連想配列が引数として渡されます。
	 *
	 * 実装例:
	 * <pre>&lt;?php
	 * //リンクラベルをコールバック関数で指定
	 * print $px-&gt;mk_link(
	 *   '/aaa/bbb.html',
	 *   function($page_info){return htmlspecialchars($page_info['title_label']);}
	 * );
	 * ?&gt;</pre>
	 *
	 * @param string $linkto リンク先のパス。PxFWのインストールパスを基点にした絶対パスで指定。
	 * @param array $options options: [as string] Link label, [as function] callback function, [as array] Any options.
	 * <dl>
	 *   <dt>mixed $options['label']</dt>
	 *	 <dd>リンクのラベルを変更します。文字列か、またはコールバック関数が利用できます。</dd>
	 *   <dt>bool $options['current']</dt>
	 *	 <dd><code>true</code> または <code>false</code> を指定します。<code>class="current"</code> を強制的に付加または削除します。このオプションが指定されない場合は、自動的に選択されます。</dd>
	 *   <dt>bool $options['no_escape']</dt>
	 *	 <dd><code>true</code> または <code>false</code> を指定します。この値が <code>false</code> の場合、リンクラベルに含まれるHTML特殊文字が自動的にエスケープされます。デフォルトは、<code>false</code>、<code>$options['label']</code>が指定された場合は自動的に <code>true</code> になります。</dd>
	 *   <dt>mixed $options['class']</dt>
	 *	 <dd>スタイルシートの クラス名 を文字列または配列で指定します。</dd>
	 * </dl>
	 * 第2引数にリンクラベルを直接指定することもできます。この場合、オプション配列は第3引数に指定します。
	 *
	 * @return string HTMLソース(aタグ)
	 */
	public function mk_link( $linkto, $options = array() ){
		$parsed_url = parse_url($linkto);
		$args = func_get_args();
		$page_info = null;
		if( $this->site() !== false ){
			$page_info = $this->site()->get_page_info($linkto);
			if( !$page_info ){
				$page_info = $this->site()->get_page_info(@$parsed_url['path']);
			}
		}
		$href = $this->href($linkto);
		$hrefc = $this->href($this->req()->get_request_file_path());
		$label = $page_info['title_label'];
		$page_id = $page_info['id'];

		$options = array();
		if( count($args) >= 2 && is_array($args[count($args)-1]) ){
			//  最後の引数が配列なら
			//  オプション連想配列として読み込む
			$options = $args[count($args)-1];
			if( is_string(@$options['label']) || (is_object(@$options['label']) && is_callable(@$options['label'])) ){
				$label = $options['label'];
				if( !is_array($options) || !array_key_exists('no_escape', $options) || is_null($options['no_escape']) ){
					$options['no_escape'] = true;
				}
			}
		}
		if( @is_string($args[1]) || (@is_object($args[1]) && @is_callable($args[1])) ){
			//  第2引数が文字列、または function なら
			//  リンクのラベルとして採用
			$label = $args[1];
			if( !is_array($options) || !array_key_exists('no_escape', $options) || is_null($options['no_escape']) ){
				$options['no_escape'] = true;
			}
		}

		$is_current = false;
		if( $this->site() !== false ){
			if( is_array($options) && array_key_exists('current', $options) && !is_null( $options['current'] ) ){
				$is_current = !@empty($options['current']);
			}elseif($href==$hrefc){
				$is_current = true;
			}elseif( $this->site()->is_page_in_breadcrumb($page_info['id']) ){
				$is_current = true;
			}
		}

		$is_popup = false;
		if( $this->site() !== false ){
			if( strpos( $this->site()->get_page_info($linkto,'layout') , 'popup' ) === 0 ){ // <- 'popup' で始まるlayoutは、ポップアップとして扱う。
				$is_popup = true;
			}
		}

		$label = (!is_null($label)?$label:$href); // labelがnullの場合、リンク先をラベルとする

		$classes = array();
		// CSSのクラスを付加
		if( is_array($options) && array_key_exists('class', $options) && is_string($options['class']) ){
			$options['class'] = preg_split( '/\s+/', trim($options['class']) );
		}
		if( is_array($options) && array_key_exists('class', $options) && is_array($options['class']) ){
			foreach($options['class'] as $class_row){
				array_push($classes, trim($class_row));
			}
		}
		if( $is_current ){
			array_push($classes, 'current');
		}

		if( is_object($label) && is_callable($label) ){
			$label = $label($page_info);
			if( !is_array($options) || !array_key_exists('no_escape', $options) || is_null($options['no_escape']) ){
				$options['no_escape'] = true;
			}
		}
		if( !@$options['no_escape'] ){
			// no_escape(エスケープしない)指示がなければ、
			// HTMLをエスケープする。
			$label = htmlspecialchars($label);
		}

		$rtn = '<a href="'.htmlspecialchars($href).'"'.(count($classes)?' class="'.htmlspecialchars(implode(' ', $classes)).'"':'').''.($is_popup?' onclick="window.open(this.href);return false;"':'').'>'.$label.'</a>';
		return $rtn;
	}

	/**
	 * ドメイン名を取得する。
	 *
	 * `$conf->domain` が設定されている場合、これを返します。
	 * 設定されていない場合は、環境変数 `$_SERVER['SERVER_NAME']` から取得して返します。
	 * ただし、Pickles 2 は ウェブサーバー上で実行されているとは限らないため、 `$_SERVER['SERVER_NAME']` は取得できないことがあります。
	 * なるべく `$conf->domain` を正しく設定する方が望ましい結果が得られます。
	 * @return string ドメイン名
	 */
	public function get_domain(){
		static $rtn;
		if( !is_null($rtn) ){
			return $rtn;
		}
		if( @strlen( $this->conf->domain ) ){
			$rtn = $this->conf->domain;
			return $rtn;
		}
		$rtn = @$_SERVER['SERVER_NAME'];
		return $rtn;
	}

	/**
	 * コンテンツルートディレクトリのパス(=install path) を取得する。
	 *
	 * コンテンツルートディレクトリは、PHPの実行ファイル (通常は `.px_execute.php`)が設置されたディレクトリを指します。
	 *
	 * 通常、これは ドキュメントルート(`$px->get_realpath_docroot()` から得られる) と同じディレクトリですが、
	 * より深い物理階層に Pickles 2 をインストールすることもできます。
	 * この場合、 `$conf->path_controot` に ドキュメントルート以下のパスを設定してください。
	 * `get_path_controot()` は、このパスを整形して返します。
	 *
	 * @return string コンテンツディレクトリのパス(HTTPクライアントから見た場合のパス)
	 */
	public function get_path_controot(){
		static $rtn;
		if( !is_null($rtn) ){
			return $rtn;
		}
		$rtn = $this->fs->normalize_path( dirname( $_SERVER['SCRIPT_NAME'] ) );
		if( $rtn != '/' ){
			$rtn .= '/';
		}
		if( $this->req()->is_cmd() ){
			$rtn = '/';
			if( @strlen( $this->conf->path_controot ) ){
				$rtn = $this->conf->path_controot;
				$rtn = preg_replace('/^(.*?)\/*$/s', '$1/', $rtn);
				$rtn = $this->fs->normalize_path($rtn);
				return $rtn;
			}
		}
		$rtn = $this->fs->normalize_path($rtn);
		return $rtn;
	}

	/**
	 * DOCUMENT_ROOT のパスを取得する。 (deprecated)
	 * このメソッドの使用は推奨されません。
	 * 代わりに `$px->get_realpath_docroot()` を使用してください。
	 * @return string ドキュメントルートのパス
	 */
	public function get_path_docroot(){
		return $this->get_realpath_docroot();
	}

	/**
	 * DOCUMENT_ROOT のパスを取得する。
	 *
	 * pickles2/px-fw-2.x@2.0.29 で `get_path_docroot()` からの名称変更として追加されました。
	 *
	 * @return string ドキュメントルートのパス
	 */
	public function get_realpath_docroot(){
		$path_controot = $this->fs->normalize_path( $this->fs()->get_realpath( $this->get_path_controot() ) );
		$path_cd = $this->fs->normalize_path( $this->fs()->get_realpath( dirname($_SERVER['SCRIPT_FILENAME']) ).DIRECTORY_SEPARATOR );
		$rtn = preg_replace( '/'.preg_quote( $path_controot, '/' ).'$/s', '', $path_cd ).DIRECTORY_SEPARATOR;
		$rtn = $this->fs->normalize_path($rtn);
		return $rtn;
	}

	/**
	 * ローカルリソースディレクトリのパスを得る。
	 *
	 * @param string $localpath_resource ローカルリソースのパス
	 * @return string ローカルリソースの実際の絶対パス
	 */
	public function path_files( $localpath_resource = null ){
		if( $this->site() !== false ){
			$tmp_page_info = $this->site()->get_current_page_info();
			$path_content = $tmp_page_info['content'];
			unset($tmp_page_info);
		}
		if( @is_null($path_content) ){
			$path_content = $this->req()->get_request_file_path();
		}

		$rtn = $this->conf->path_files;
		$data = array(
			'dirname'=>$this->fs->normalize_path(dirname($path_content)),
			'filename'=>basename($this->fs()->trim_extension($path_content)),
			'ext'=>strtolower($this->fs()->get_extension($path_content)),
		);
		$rtn = str_replace( '{$dirname}', $data['dirname'], $rtn );
		$rtn = str_replace( '{$filename}', $data['filename'], $rtn );
		$rtn = str_replace( '{$ext}', $data['ext'], $rtn );
		$rtn = preg_replace( '/^\/*/', '/', $rtn );
		$rtn = preg_replace( '/\/*$/', '', $rtn ).'/';

		$rtn = $rtn.$localpath_resource;
		if( $this->fs()->is_dir('./'.$rtn) ){
			$rtn .= '/';
		}
		$rtn = $this->href( $rtn );
		$rtn = $this->fs->normalize_path($rtn);
		$rtn = preg_replace( '/^\/+/', '/', $rtn );
		return $rtn;
	}//path_files()

	/**
	 * ローカルリソースディレクトリのサーバー内部パスを得る。
	 *
	 * @param string $localpath_resource ローカルリソースのパス
	 * @return string ローカルリソースのサーバー内部パス
	 */
	public function realpath_files( $localpath_resource = null ){
		$rtn = $this->path_files( $localpath_resource );
		$rtn = $this->fs()->get_realpath( $rtn );
		$rtn = $this->fs()->get_realpath( $this->get_realpath_docroot().$rtn );
		return $rtn;
	}//realpath_files()

	/**
	 * ローカルリソースのキャッシュのパスを得る。
	 *
	 * ローカルリソースキャッシュディレクトリは、
	 * `$conf->public_cache_dir` の設定により決定されます。
	 *
	 * `$conf->public_cache_dir` が設定されていない場合に `false` を返します。
	 *
	 * @param string $localpath_resource ローカルリソースのパス
	 * @return string ローカルリソースキャッシュのパス
	 */
	public function path_files_cache( $localpath_resource = null ){
		if( !strlen( @$this->conf()->public_cache_dir ) ){
			return false;
		}
		if( $this->site() !== false ){
			$tmp_page_info = $this->site()->get_current_page_info();
			$path_content = $tmp_page_info['content'];
			unset($tmp_page_info);
		}
		if( @is_null($path_content) ){
			$path_content = $this->req()->get_request_file_path();
		}

		$path_original = $this->get_path_controot().$path_content;
		$path_original = $this->fs()->get_realpath($this->fs()->trim_extension($path_original).'_files/'.$localpath_resource);
		$rtn = $this->get_path_controot().'/'.$this->conf()->public_cache_dir.'/c'.$path_content;
		$rtn = $this->fs()->get_realpath($this->fs()->trim_extension($rtn).'_files/'.$localpath_resource);
		$this->fs()->mkdir_r( dirname( $this->get_realpath_docroot().$rtn ) );

		if( file_exists( $this->get_realpath_docroot().$path_original ) ){
			if( is_dir($this->get_realpath_docroot().$path_original) ){
				$this->fs()->mkdir_r( $this->get_realpath_docroot().$rtn );
			}else{
				$this->fs()->mkdir_r( dirname( $this->get_realpath_docroot().$rtn ) );
			}
			$this->fs()->copy_r( $this->get_realpath_docroot().$path_original, $this->get_realpath_docroot().$rtn );
		}

		$rtn = $this->fs()->normalize_path($rtn);
		$rtn = preg_replace( '/^\/+/', '/', $rtn );
		$this->add_relatedlink($rtn);
		return $rtn;
	}//path_files_cache()

	/**
	 * ローカルリソースのキャッシュディレクトリのサーバー内部パスを得る。
	 *
	 * ローカルリソースキャッシュディレクトリは、
	 * `$conf->public_cache_dir` の設定により決定されます。
	 *
	 * `$conf->public_cache_dir` が設定されていない場合に `false` を返します。
	 *
	 * @param string $localpath_resource ローカルリソースのパス
	 * @return string ローカルリソースキャッシュのサーバー内部パス
	 */
	public function realpath_files_cache( $localpath_resource = null ){
		if( !strlen( @$this->conf()->public_cache_dir ) ){
			return false;
		}
		$rtn = $this->path_files_cache( $localpath_resource );
		$rtn = $this->fs()->get_realpath( $this->get_realpath_docroot().$rtn );
		return $rtn;
	}//realpath_files_cache()


	/**
	 * コンテンツ別の非公開キャッシュディレクトリのサーバー内部パスを得る。
	 *
	 * @param string $localpath_resource ローカルリソースのパス
	 * @return string コンテンツ別の非公開キャッシュのサーバー内部パス
	 */
	public function realpath_files_private_cache( $localpath_resource = null ){
		if( $this->site() !== false ){
			$tmp_page_info = $this->site()->get_current_page_info();
			$path_content = $tmp_page_info['content'];
			unset($tmp_page_info);
		}
		if( @is_null($path_content) ){
			$path_content = $this->req()->get_request_file_path();
		}

		$path_original = $this->get_path_controot().$path_content;
		$path_original = $this->fs()->get_realpath($this->fs()->trim_extension($path_original).'_files/'.$localpath_resource);
		$rtn = $this->get_realpath_homedir().'/_sys/ram/caches/c'.$path_content;
		$rtn = $this->fs()->get_realpath($this->fs()->trim_extension($rtn).'_files/'.$localpath_resource);
		$this->fs()->mkdir_r( dirname( $rtn ) );

		$rtn = $this->fs()->normalize_path( $rtn );
		$rtn = preg_replace( '/^\/+/', '/', $rtn );
		$rtn = $this->fs()->get_realpath( $rtn );
		return $rtn;
	}//realpath_files_private_cache()

	/**
	 * プラグイン別公開キャッシュのパスを得る。
	 *
	 * プラグイン別公開キャッシュディレクトリは、
	 * `$conf->public_cache_dir` の設定により決定されます。
	 *
	 * `$conf->public_cache_dir` が設定されていない場合に `false` を返します。
	 *
	 * @param string $localpath_resource リソースのパス
	 * @return string 公開キャッシュのパス
	 */
	public function path_plugin_files( $localpath_resource = null ){
		if( !strlen( @$this->conf()->public_cache_dir ) ){
			return false;
		}
		$rtn = $this->get_path_controot().'/'.$this->conf()->public_cache_dir.'/p/'.urlencode($this->proc_id).'/';
		$rtn = $this->fs()->get_realpath( $rtn.'/' );
		if( !$this->fs()->is_dir( $this->get_realpath_docroot().$rtn ) ){
			$this->fs()->mkdir_r( $this->get_realpath_docroot().$rtn );
		}
		if( !strlen( $localpath_resource ) ){
			return $this->fs()->normalize_path($rtn);
		}
		$rtn = $this->fs()->get_realpath( $rtn.'/'.$localpath_resource );
		if( $this->fs()->is_dir( $this->get_realpath_docroot().$rtn ) ){
			$rtn .= '/';
		}
		if( !$this->fs()->is_dir( dirname($this->get_realpath_docroot().$rtn) ) ){
			$this->fs()->mkdir_r( dirname($this->get_realpath_docroot().$rtn) );
		}
		$rtn = $this->fs()->normalize_path($rtn);
		$rtn = preg_replace( '/^\/+/', '/', $rtn );
		$this->add_relatedlink($rtn);
		return $rtn;
	}

	/**
	 * プラグイン別公開キャッシュのサーバー内部パスを得る。
	 *
	 * プラグイン別公開キャッシュディレクトリは、
	 * `$conf->public_cache_dir` の設定により決定されます。
	 *
	 * `$conf->public_cache_dir` が設定されていない場合に `false` を返します。
	 *
	 * @param string $localpath_resource リソースのパス
	 * @return string プライベートキャッシュのサーバー内部パス
	 */
	public function realpath_plugin_files( $localpath_resource = null ){
		if( !strlen( @$this->conf()->public_cache_dir ) ){
			return false;
		}
		$rtn = $this->path_plugin_files( $localpath_resource );
		$rtn = $this->fs()->get_realpath( $this->get_realpath_docroot().$rtn );
		return $rtn;
	}

	/**
	 * プラグイン別プライベートキャッシュのサーバー内部パスを得る。
	 *
	 * @param string $localpath_resource リソースのパス
	 * @return string プライベートキャッシュのサーバー内部パス
	 */
	public function realpath_plugin_private_cache( $localpath_resource = null ){
		$lib_realpath = $this->get_realpath_homedir().'_sys/ram/caches/p/'.urlencode($this->proc_id).'/';
		$rtn = $this->fs()->get_realpath( $lib_realpath.'/' );
		if( !$this->fs()->is_dir( $rtn ) ){
			$this->fs()->mkdir_r( $rtn );
		}
		if( !strlen( $localpath_resource ) ){
			return $rtn;
		}
		$rtn .= '/'.$localpath_resource;
		// if( $this->fs()->is_dir( $rtn ) ){
		// 	$rtn .= '/';
		// }
		$rtn = $this->fs()->get_realpath( $rtn );
		if( !$this->fs()->is_dir( dirname($rtn) ) ){
			$this->fs()->mkdir_r( dirname($rtn) );
		}
		return $rtn;
	}

	/**
	 * テキストを、指定の文字セットに変換する。
	 *
	 * @param mixed $text テキスト
	 * @param string $encode 変換後の文字セット。省略時、`mb_internal_encoding()` から取得
	 * @param string $encodefrom 変換前の文字セット。省略時、自動検出
	 * @return string 文字セット変換後のテキスト
	 */
	public function convert_encoding( $text, $encode = null, $encodefrom = null ){
		if( !is_callable( 'mb_internal_encoding' ) ){ return $text; }
		if( !strlen( $encode ) ){ $encode = mb_internal_encoding(); }
		if( !strlen( $encodefrom ) ){ $encodefrom = mb_internal_encoding().',UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP,JIS,ASCII'; }

		switch( strtolower( $encode ) ){
			case 'sjis':
			case 'sjis-win':
			case 'shift_jis':
			case 'shift-jis':
			case 'x-sjis':
				// ※ 'ms_kanji'、'windows-31j'、'cp932' は、もともと SJIS-win になる
				$encode = 'SJIS-win';
				break;
			case 'eucjp':
			case 'eucjp-win':
			case 'euc-jp':
				$encode = 'eucJP-win';
				break;
		}

		if( is_array( $text ) ){
			$RTN = array();
			if( !count( $text ) ){ return $text; }
			$TEXT_KEYS = array_keys( $text );
			foreach( $TEXT_KEYS as $Line ){
				$KEY = mb_convert_encoding( $Line , $encode , $encodefrom );
				if( is_array( $text[$Line] ) ){
					$RTN[$KEY] = $this->convert_encoding( $text[$Line] , $encode , $encodefrom );
				}else{
					$RTN[$KEY] = @mb_convert_encoding( $text[$Line] , $encode , $encodefrom );
				}
			}
		}else{
			if( !strlen( $text ) ){ return $text; }
			$RTN = @mb_convert_encoding( $text , $encode , $encodefrom );
		}
		return $RTN;
	}


	/**
	 * パス文字列を受け取り、種類を判定する。
	 *
	 * 次の基準で判定されます。
	 *
	 * - `javascript:` から始まる場合 => 'javascript'
	 * - `#` から始まる場合 => 'anchor'
	 * - `http://` などURLスキーマ名から始まる場合, `//` から始まる場合 => 'full_url'
	 * - その他で `alias:` から始まる場合 => 'alias'
	 * - `{$xxxx}` または `{*xxxx}` を含む場合 => 'dynamic'
	 * - `/` から始まる場合 => 'normal'
	 * - どれにも当てはまらない不明な形式の場合に、`false` を返します。
	 *
	 * このメソッドは、以前は `$site` に実装されていました。
	 * `$conf->paths_enable_sitemap` が導入され、 `$site` が存在しない場合が考慮されるようになったことにより、
	 * `$px` に移管されました。
	 *
	 * @param string $path 調べるパス
	 * @return string|bool 判定結果
	 */
	public function get_path_type( $path ) {
		if( preg_match( '/^(?:alias[0-9]*\:)?javascript\:/i' , $path ) ) {
			//  javascript: から始まる場合
			//  サイトマップ上での重複を許容するために、
			//  自動的にalias扱いとなることを考慮した正規表現。
			$path_type = 'javascript';
		} else if( preg_match( '/^(?:alias[0-9]*\:)?\#/' , $path ) ) {
			//  # から始まる場合
			//  サイトマップ上での重複を許容するために、
			//  自動的にalias扱いとなることを考慮した正規表現。
			$path_type = 'anchor';
		} else if( preg_match( '/^(?:alias[0-9]*\:)?[a-zA-Z0-90-9]+\:\/\//' , $path ) ) {
			//  http:// などURLスキーマから始まる場合
			//  サイトマップ上での重複を許容するために、
			//  自動的にalias扱いとなることを考慮した正規表現。
			$path_type = 'full_url';
		} else if( preg_match( '/^alias[0-9]*\:/' , $path ) ) {
			//  alias:から始まる場合
			//  サイトマップデータ上でpathは一意である必要あるので、
			//  alias と : の間に、後から連番を降られる。
			//  このため、数字が含まれている場合を考慮した。(@tomk79)
			$path_type = 'alias';
		} else if( preg_match( '/\{(?:\$|\*)(?:[a-zA-Z0-9\_\-]*)\}/' , $path ) ) {
			//  {$xxxx} または {*xxxx} を含む場合(ダイナミックパス)
			$path_type = 'dynamic';
		} else if( preg_match( '/^\/\//' , $path ) ) {
			//  //から始まる場合
			$path_type = 'full_url';
		} else if( preg_match( '/^\//' , $path ) ) {
			//  /から始まる場合
			$path_type = 'normal';
		} else {
			//  どれにも当てはまらない場合はfalseを返す
			$path_type = false;
		}
		return $path_type;
	}//get_path_type()



	/**
	 * HTTP ヘッダを送信する。
	 *
	 * このメソッドは、 PHP の `header()` の代わりに使用します。
	 *
	 * PHP の `header()` は、SAPI に依存しており、 CLI SAPI 上ではヘッダーを送信できません。
	 * このメソッドは CLI SAPI の環境下でも、HTTPレスポンスヘッダーの処理をエミュレートするために用意されました。
	 * `$px->header()` を通して送信されたHTTPヘッダーは、 `$px->header_list()` から取得でき、 JSON形式の出力(コマンドラインオプション `-o json` を付加して実行)時、`header` に格納されます。
	 *
	 * ただし、 `$px->header()` は、次のヘッダーは扱いません。
	 *
	 * - HTTPステータスコード : 代わりに `$px->set_status()` を使用してください。 `$px->header()` にこのヘッダーを渡すと、 `$px->set_status()` にフォワードされます。
	 * - X-PXFW-ERROR : 代わりに `$px->error()` を使用してください。
	 * - X-PXFW-RELATEDLINK : 代わりに `$px->add_relatedlink()` を使用してください。
	 *
	 * @param string $string ヘッダー文字列
	 * @param bool $replace ヘッダが 前に送信された類似のヘッダを置換するか、または、同じ形式の二番目の ヘッダを追加するかどうかを指定する
	 * @return null 値を返しません。
	 */
	public function header($string, $replace = true){
		if( preg_match( '/^(.*?)\:(.*)$/', $string, $matched ) ){
			$key = @strtolower(trim($matched[1]));
			$val = @trim($matched[2]);
			switch($key){
				case 'content-type': $key = 'Content-type'; break;
			}
			if( $replace === true ){
				unset( $this->response_headers[$key] );
			}
			if( !@is_array($this->response_headers[$key]) ){
				$this->response_headers[$key] = array();
			}
			array_push( $this->response_headers[$key], $val );
		}elseif( preg_match( '/^HTTP\/([0-9\.]*?) ([0-9]{3}) (.*)$/', $string, $matched ) ){
			// ステータスコードは $px が管理しているので転送する
			$this->set_status($matched[2], $matched[3]);
		}

		@header($string, $replace);
		return;
	}

	/**
	 * 送信した (もしくは送信される予定の) レスポンスヘッダの一覧を返す。
	 *
	 * `$px->header()` から送られたHTTPレスポンスヘッダーの一覧を返します。
	 * このメソッドは CLI SAPI の環境下でも、HTTPレスポンスヘッダーの処理をエミュレートするために用意されました。
	 * 従って、実際に送信されたヘッダー情報を管理するものではありません。
	 *
	 * @return array ヘッダを、数値添字の配列で返します。
	 */
	public function header_list(){
		$rtn = array();
		foreach($this->response_headers as $key=>$vals){
			foreach($vals as $val){
				array_push($rtn, $key.': '.$val);
			}
		}
		return $rtn;
	}


	/**
	 * アプリケーションロックする。
	 *
	 * @param string $app_name アプリケーションロック名
	 * @param int $expire 有効時間(秒) (省略時: 60秒)
	 * @return bool ロック成功時に `true`、失敗時に `false` を返します。
	 */
	public function lock( $app_name, $expire = 60 ){
		$lockfilepath = $this->get_realpath_homedir().'_sys/ram/applock/'.urlencode($app_name).'.lock.txt';
		$timeout_limit = 5;

		if( !@is_dir( dirname( $lockfilepath ) ) ){
			$this->fs()->mkdir_r( dirname( $lockfilepath ) );
		}

		// PHPのFileStatusCacheをクリア
		clearstatcache();

		$i = 0;
		while( $this->is_locked( $app_name, $expire ) ){
			$i ++;
			if( $i >= $timeout_limit ){
				return false;
				break;
			}
			sleep(1);

			// PHPのFileStatusCacheをクリア
			clearstatcache();
		}
		$src = '';
		$src .= 'ProcessID='.getmypid()."\r\n";
		$src .= @date( 'Y-m-d H:i:s' , time() )."\r\n";
		$RTN = $this->fs()->save_file( $lockfilepath , $src );
		return	$RTN;
	} // lock()

	/**
	 * アプリケーションロックされているか確認する。
	 *
	 * @param string $app_name アプリケーションロック名
	 * @param int $expire 有効時間(秒) (省略時: 60秒)
	 * @return bool ロック中の場合に `true`、それ以外の場合に `false` を返します。
	 */
	public function is_locked( $app_name, $expire = 60 ){
		$lockfilepath = $this->get_realpath_homedir().'_sys/ram/applock/'.urlencode($app_name).'.lock.txt';
		$lockfile_expire = $expire;

		// PHPのFileStatusCacheをクリア
		clearstatcache();

		if( $this->fs()->is_file($lockfilepath) ){
			if( ( time() - filemtime($lockfilepath) ) > $lockfile_expire ){
				// 有効期限を過ぎていたら、ロックは成立する。
				return false;
			}
			return true;
		}
		return false;
	} // is_locked()

	/**
	 * アプリケーションロックを解除する。
	 *
	 * @param string $app_name アプリケーションロック名
	 * @return bool ロック解除成功時に `true`、失敗時に `false` を返します。
	 */
	public function unlock( $app_name ){
		$lockfilepath = $this->get_realpath_homedir().'_sys/ram/applock/'.urlencode($app_name).'.lock.txt';

		// PHPのFileStatusCacheをクリア
		clearstatcache();

		return @unlink( $lockfilepath );
	} // unlock()

	/**
	 * アプリケーションロックファイルの更新日を更新する。
	 *
	 * @param string $app_name アプリケーションロック名
	 * @return bool 成功時に `true`、失敗時に `false` を返します。
	 */
	public function touch_lockfile( $app_name ){
		$lockfilepath = $this->get_realpath_homedir().'_sys/ram/applock/'.urlencode($app_name).'.lock.txt';

		// PHPのFileStatusCacheをクリア
		clearstatcache();
		if( !is_file( $lockfilepath ) ){
			return false;
		}

		return touch( $lockfilepath );
	} // touch_lockfile()

	/**
	 * Pickles 2 の SVG ロゴソースを取得する。
	 * @param array $opt オプション
	 * <dl>
	 * 	<dt>color</dt><dd>ロゴの色コード</dd>
	 * </dl>
	 * @return string Pickles 2 ロゴの SVG ソースコード
	 */
	public function get_pickles_logo_svg( $opt = array() ){
		$logo_color = '#f6f6f6';
		if( @strlen( $opt['color'] ) ){
			$logo_color = $opt['color'];
		}
		ob_start();
		?>
<svg version="1.1" id="pickles2-logo" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px"
	 y="0px" width="40px" height="40px" viewBox="0 0 42.5 42.5" style="enable-background:new 0 0 42.5 42.5;" xml:space="preserve">
<g>
	<path fill="<?php print htmlspecialchars($logo_color); ?>" d="M1.2,1.2v40.1h40.1V1.2H1.2z M2.3,40.2V2.3h37.8v37.8H2.3z"/>
	<g>
		<path fill="<?php print htmlspecialchars($logo_color); ?>" d="M11.5,14.1c3.4,0,5,1.4,5,4.1c0,2.7-1.8,4.5-5.3,4.5c-0.5,0-1.1,0-1.8-0.1v5.7H8.7V14.5
			C9.6,14.3,10.6,14.2,11.5,14.1z M13.8,21.6c1.1-0.5,1.9-1.7,1.9-3.3c0-2.4-1.4-3.5-4.3-3.5c-0.7,0-1.3,0.1-2.1,0.2v7
			c0.5,0.1,1.1,0.1,1.6,0.1C12.1,22.1,13,22,13.8,21.6z"/>
		<path fill="<?php print htmlspecialchars($logo_color); ?>" d="M18.9,18.8l2.6,3.9l2.6-3.9h0.6c0,0.2-0.2,0.5-0.3,0.7L22,23.3l3,4.4c0.1,0.2,0.3,0.4,0.4,0.7h-0.7l-3.1-4.5
			l-3.1,4.5h-0.7c0-0.2,0.2-0.4,0.4-0.7l3-4.5l-2.6-3.7c-0.1-0.2-0.3-0.4-0.4-0.7H18.9z"/>
		<path fill="<?php print htmlspecialchars($logo_color); ?>" d="M30.7,14.1c2.6,0,3.8,1.7,3.8,3.8c0,2.8-2,5.1-4.6,7.8l-1.9,2h6.8l0,0.6h-7.6l-0.1-0.7l1.7-1.8
			c3.3-3.4,4.9-5.4,4.9-7.8c0-1.8-1-3.2-3-3.2c-1,0-2,0.4-2.9,1l-0.4-0.5C28.4,14.5,29.5,14.1,30.7,14.1z"/>
	</g>
</g>
</svg>
<?php
		return ob_get_clean();
	}//get_pickles_logo_svg()

}
