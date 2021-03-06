# Pickles Framework 2

<table>
  <thead>
    <tr>
      <th></th>
      <th>Linux</th>
      <th>Windows</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <th>master</th>
      <td align="center">
        <a href="https://travis-ci.org/pickles2/px-fw-2.x"><img src="https://secure.travis-ci.org/pickles2/px-fw-2.x.svg?branch=master"></a>
      </td>
      <td align="center">
        <a href="https://ci.appveyor.com/project/tomk79/px-fw-2-x"><img src="https://ci.appveyor.com/api/projects/status/bq8v3bgfrhbvr6rv/branch/master?svg=true"></a>
      </td>
    </tr>
    <tr>
      <th>develop</th>
      <td align="center">
        <a href="https://travis-ci.org/pickles2/px-fw-2.x"><img src="https://secure.travis-ci.org/pickles2/px-fw-2.x.svg?branch=develop"></a>
      </td>
      <td align="center">
        <a href="https://ci.appveyor.com/project/tomk79/px-fw-2-x"><img src="https://ci.appveyor.com/api/projects/status/bq8v3bgfrhbvr6rv/branch/develop?svg=true"></a>
      </td>
    </tr>
  </tbody>
</table>


Pickles Framework(PxFW) は、静的で大きなウェブサイトを効率よく構築できる オープンソースのHTML生成ツールです。<br />
データベース不要、PHPが動くサーバーに手軽に導入でき、プロトタイプ制作を中心に進めるような柔軟な制作スタイルを実現します。

Pickles2 は、[PxFW-1.x](https://github.com/tomk79/PxFW-1.x) の後継です。
主な改善点は次の通りです。

- `composer` からインストールできるようになりました。
- 機能の追加、拡張が手軽にできるようになりました。
- コマンドラインからの実行が改善され、外部のツールやスクリプトとの連携が容易になりました。
- その他、よりシンプルに利用できるよう、多くの機能が改善されました。



## インストール手順 - Install


Pickles Framework 2.x はラッパーである [Pickles 2](https://github.com/pickles2/pickles2) からの利用をおすすめします。

```
$ cd {$documentRoot}
$ composer create-project pickles2/pickles2 ./
```

`.px_execute.php` の置かれたディレクトリがドキュメントルートになるよう、ウェブサーバーを設定してください。

Pickles Framework が書き込みを行うディレクトリがあります。次のコマンドは、書き込み権限を付与するためのものです。すでに権限がある場合は実行する必要はありません。

```
$ chmod -R 777 ./px-files/_sys
$ chmod -R 777 ./caches
```



## システム要件 - System Requirement

- Linux系サーバ または Windowsサーバ
- Apache
  - mod_rewrite が利用可能であること
  - .htaccess が利用可能であること
- PHP 5.4 以上
  - mbstring が有効に設定されていること
  - PDO SQLiteドライバー (PDO_SQLITE) が有効に設定されていること
  - safe_mode が無効に設定されていること


## 更新履歴 - Change log

### Pickles Framework 2.0.29 (2017年2月6日)

- サイトマップ項目に `proc_type` を追加。 `$conf->paths_proc_type` と同様の効果だが、サイトマップ上で設定できるようになった。
- クラス `site`, `bowl`, `pxcmd` のAPIを外部から呼び出せるようにした。
- パブリッシュコマンドの最後に、検出したアラートログを表示するようにした。
- パブリッシュコマンドの最後に、パブリッシュ処理にかかった時間を表示するようにした。
- `$px->get_path_homedir()` を `$px->get_realpath_homedir()` に改名。(古いメソッド名の実装は残されているが非推奨)
- `$px->get_path_docroot()` を `$px->get_realpath_docroot()` に改名。(古いメソッド名の実装は残されているが非推奨)
- メソッド名の改名に合わせて、 `PX=api.*` もそれぞれ改名。(古い名前のAPIの実装は残されているが非推奨)
- パブリッシュのパフォーマンスを改善。
- デフォルトの Content-type を proc_type の値を参照して決定するように変更した。
- `$px->header()`, `$px->header_list()` を追加。
- JSONでの出力時(コマンドラインオプション `-o json` 付加時)、 `header` に HTTPヘッダー情報が出力されるようになった。

### Pickles Framework 2.0.28 (2016年12月8日)

- Windowsサーバーで、サイトマップキャッシュが排他ロックされて更新に失敗することがある問題を修正。
- パブリッシュプラグインを `before_sitemap` に設定しても動作するように変更。サイトマップ生成のパフォーマンスが改善する。
- `$site` が既にセットされている場合に、再生成せずそのまま利用するようになった。実質、 `before_sitemap` に設定したプラグインから `$site` の挙動を変更することが可能になった。

### Pickles Framework 2.0.27 (2016年11月21日)

- サイトマップキャッシュ生成中の2件目以降のリクエストに関するパフォーマンスを改善。待ち時間がなくなった。
- サイトマップキャッシュ生成開始から60分経過しても進捗した形跡がなければ、再生成するようになった。
- サイトマップキャッシュ生成中の2件目以降のリクエストで、古いキャッシュが利用できない場合、仮のトップページがセットされるようになった。
- サイトマップキャッシュ生成中の2件目以降のリクエストで、古いキャッシュが利用できる場合、それを利用するようになった。
- PXコマンド `PX=publish.version`, `PX=clearcache.version` を追加。
- パブリッシュ時、エラーを含むページも、削除されずに出力されるようになった。

### Pickles Framework 2.0.26 (2016年10月17日)

- サイトマップキャッシュ生成中の2件目以降のリクエストに関するパフォーマンスが改善した。
- 依存ライブラリ michelf/php-markdown, leafo/scssphp のバージョンを更新。
- `PX=api` がサイトマップを利用できない場合に、サイトマップ操作のAPIが `false` を返すようになった。

### Pickles Framework 2.0.25 (2016年9月28日)

- サイトマップに載っていないファイルを単体でパブリッシュできない不具合を修正。
- パブリッシュ範囲をファイル単体で指定した場合の、2重拡張子によるファイル名の揺れを吸収するようになった。

### Pickles Framework 2.0.24 (2016年9月22日)

- proc_type が `pass` 、 `ignore` の場合に、 `$conf->funcs->before_sitemap`, `$conf->funcs->before_content` に設定されたPXコマンドが実行されるようになった。
- パブリッシュ範囲に具体的なファイル名を指定した場合のパフォーマンスが向上した。

### Pickles Framework 2.0.23 (2016年8月24日)

- パブリッシュのオプション `keep_cache` を追加。キャッシュの消去と再生成のプロセスをスキップできるようになった。
- パブリッシュのオプション `paths_region` を追加。パブリッシュ対象範囲を複数指定できるようになった。
- コマンドラインからの起動時にも、 `$_SERVER['DOCUMENT_ROOT']` を使用できるようになった。
- サイトマップに含まれる外部URLが `/index.html` で終わっている場合に、ページとして正しく処理できない不具合を修正。

### Pickles Framework 2.0.22 (2016年7月27日)

- コンフィグ項目 `$conf->paths_enable_sitemap` を追加。
- `$conf->paths_proc_type` に、新しい処理方法 pass を追加。デフォルトを pass に変更。
- `$conf->paths_proc_type` が direct のときに、二重拡張子の処理を適用できるようになった。
- 他プロセスがサイトマップキャッシュを生成中にアクセスした場合にサイトマップキャッシュ生成をスキップするアプリケーションロック機能を追加。
- Windows で Apache 上で実行する場合に、 `$px->get_path_controot()` 等のパスがずれてしまう不具合を修正。

### Pickles Framework 2.0.21 (2016年7月14日)

- `$conf->paths_proc_type` の設定を、前方マッチから完全マッチに変更。 ディレクトリの指定等は、ワイルドカード `*` を使って表現する方針で統一。
- `PX=publish` に、パブリッシュ対象外のパスをコンフィグオプションで設定できる機能を追加。 (コマンドラインオプションで除外する方法は従来から存在していた)
- 公開キャッシュ と `_sys/ram/*` のディレクトリが存在しない場合に、作成を試みるように変更。
- `path_publish_dir` と `contents_manifesto` を設定しない場合 Notice が起こらないように変更。
- sitemaps ディレクトリが存在しない場合に Notice が起こらないように変更。
- サイトマップが最小構成の場合に、Noticeレベルのエラーが発生する不具合を修正。
- サイトマップ解析時に、Libre Office, Open Office 形式の一時ファイルを無視するように変更。
- その他の細かい不具合修正。

### Pickles Framework 2.0.20 (2016年4月7日)

- サイトマップCSVの定義列がアスタリスク始まりではない(または空欄)の列がある場合、定義行が存在しないとみなしてしまう問題を修正。

### Pickles Framework 2.0.19 (2016年3月15日)

- コンフィグ項目 $conf->copyright を追加。

### Pickles Framework 2.0.18 (2016年2月22日)

- パブリッシュオプション paths_ignore に指定したパスが、パブリッシュディレクトリから削除されてしまう不具合を修正。

### Pickles Framework 2.0.17 (2016年2月18日)

- 範囲指定したパブリッシュのディレクトリスキャンにかかるパフォーマンスを改善。
- ?PX=publish のオプション paths_ignore を追加。

### Pickles Framework 2.0.16 (2016年1月2日)

- パブリッシュ実行中に、パブリッシュ先ディレクトリに都度コピーする機能が無効になる場合がある不具合を修正。
- その他、軽微な不具合の修正。

### Pickles Framework 2.0.15 (2015年11月9日)

- Actor機能追加。
- pickles.php と px.php を分離。テストを書きやすくするための配慮により。
- パブリッシュ時、サイトマップ上でプロトコル名、またはドメイン名から始まるリンク先の場合はスキップするように変更。

### Pickles Framework 2.0.14 (2015年10月23日)

- Markdownプロセッサーが、head と foot を処理しないように変更。
- .ignore を含むパスへのリクエストを、.htaccess で除外するように変更。

### Pickles Framework 2.0.13 (2015年9月4日)

- サイトマップキャッシュに SQLite を導入。ページ数の多いサイトの処理が高速化。
- デフォルトで bowl "foot" の定義を新たに追加。
- サイトマップに、サイト外のURLを組み込めるようになった。

### Pickles Framework 2.0.12 (2015年8月3日)

- $conf->path_files を追加。
- $conf->default_timezone を追加。
- $conf->path_phpini を追加。
- コマンドラインオプション --command-php, -c を追加。
- その他、不具合の修正など。

### Pickles Framework 2.0.11 (2015年7月2日)

- パブリッシュに時間が掛かり過ぎるときに、タイムアウトが発生して途中終了することがある不具合を修正。
- パブリッシュログに ファイルサイズ と ファイル個々にの処理にかかった時間(microtime) を記載するようになった。
- その他、軽微な不具合の修正など。


## 開発者向け情報 - for Developer


### テスト - Test

```
$ cd {$documentRoot}
$ php vendor/phpunit/phpunit/phpunit
```


### ドキュメント出力 - phpDocumentor

```
$ composer run-script documentation
```


## ライセンス - License

Copyright (c)2001-2016 Tomoya Koyanagi, and Pickles 2 Project<br />
MIT License https://opensource.org/licenses/mit-license.php


## 作者 - Author

- Tomoya Koyanagi <tomk79@gmail.com>
- website: <http://www.pxt.jp/>
- Twitter: @tomk79 <http://twitter.com/tomk79/>
