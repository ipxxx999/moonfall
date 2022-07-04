<?php

/******************** ABOUT ******************************
MySQL2 App, a script to import a MySQL/MariaDB
schema to AXP format for use with AppGini.
(C) Copyright 2022 Software.
**********************************************************/

new M2A;

class M2A {
	private $dbCredentials;
	private $link;
	private $axp;
	private $tables;
	private $error;

	public function __construct() {
		/******************** workflow ***************************/
		$this->dbCredentials = $this->getDbCredentials();
		$this->link = $this->connectToDb();

		$this->tables = $this->getAppTableNames();
		$this->axp = $this->importDbToAxp();

		$this->saveAndDownloadAxp();
		/******************** end of workflow ********************/			
	}

	private function importDbToAxp() {
		$axp = "<database>";
		$axp .= "<databaseName>{$this->dbCredentials['dbName']}</databaseName>";
		$axp .= $this->getAppEncodingXml();
		$axp .= $this->getAppTablesXml();
		$axp .= "</database>";

		return $axp;
	}

	private function getAppTablesXml() {
		$axp = '';

		foreach($this->tables as $tn) {
			$axp .= implode('', [
				'<table>',
				"<name>$tn</name>",
				'<caption><![CDATA[' . $this->caption($tn) . ']]></caption>',
				'<allowInsert>False</allowInsert>',
				'<allowUpdate>False</allowUpdate>',
				'<allowCSV>True</allowCSV>',
				'<allowDelete>False</allowDelete>',
				'<allowDeleteOfParents>False</allowDeleteOfParents>',
				'<allowFilters>True</allowFilters>',
				'<filterFirst>False</filterFirst>',
				'<allowPrint>True</allowPrint>',
				'<allowMassDelete>False</allowMassDelete>',
				'<homepageShowCount>False</homepageShowCount>',
				'<allowDVPrint>True</allowDVPrint>',
				'<allowSavingFilters>False</allowSavingFilters>',
				'<allowSelection>True</allowSelection>',
				'<hideSaveNewWhenEditing>False</hideSaveNewWhenEditing>',
				'<allowSorting>True</allowSorting>',
				'<description><![CDATA[Table imported using MySQL2AppGini on ' . date('Y-m-d') . ']]></description>',
				'<detailViewLabel><![CDATA[Detail View]]></detailViewLabel>',
				'<hideTableView>True</hideTableView>',
				'<quickSearch>1</quickSearch>',
				'<recordsPerPage>10</recordsPerPage>',
				'<redirectAfterInsert><![CDATA[]]></redirectAfterInsert>',
				'<filterCount>10</filterCount>',
				'<lstChildrenLinks></lstChildrenLinks>',
				'<separateDV>True</separateDV>',
				'<hideLinkHomepage>False</hideLinkHomepage>',
				'<hideLinkNavMenu>False</hideLinkNavMenu>',
				'<defaultSortField>0</defaultSortField>',
				'<defaultSortDirection>asc</defaultSortDirection>',
				'<childrenLinksTV><![CDATA[]]></childrenLinksTV>',
				'<parentChildrenSettings><![CDATA[]]></parentChildrenSettings>',
				'<tableIcon></tableIcon>',
				'<tablePaginationAlignment>0</tablePaginationAlignment>',
				'<enableDVABFloating>True</enableDVABFloating>',
				'<groupID>0</groupID>',
				'<allowHomepageAddNew>False</allowHomepageAddNew>',
				'<TVTemplateID>horizontal</TVTemplateID>',
				'<TVTemplateHasTitle>False</TVTemplateHasTitle>',
				'<TVTemplateHideCaptions>False</TVTemplateHideCaptions>',
				'<TVTemplateImageWidth>25%</TVTemplateImageWidth>',
				'<TVClasses></TVClasses>',
				'<DVClasses></DVClasses>',
				'<technicalDocumentation><![CDATA[]]></technicalDocumentation>',
				'<autofocusField>-1</autofocusField>',
				$this->getTableFieldsXml($tn),
				'</table>',
			]);
		}

		return $axp;
	}

	private function getAppEncodingXml() {
		$dbName = mysqli_real_escape_string($this->link, $this->dbCredentials['dbName']);
		$res = @mysqli_query(
			$this->link, 
			"SELECT DEFAULT_CHARACTER_SET_NAME
			FROM `information_schema`.`SCHEMATA`
			WHERE `SCHEMA_NAME`='$dbName'"
		);
		if(!$res || !($row = mysqli_fetch_row($res))) return '';

		$appEncoding = [
			'latin1' => 'iso-8859-1',
			'utf8' => 'UTF-8',
			'cp1256' => 'Windows-1256',
			'latin7' => 'iso-8869-4',
			'cp1257' => 'Windows-1257',
			'latin2' => 'iso-8859-2',
			'cp1250' => 'Windows-1250',
			'gb2312' => 'gb2312',
			'gbk' => 'HZ-GB-2312',
			'big5' => 'big5',
			'koi8r' => 'koi8-r',
			'koi8u' => 'koi8-u',
			'cp1251' => 'Windows-1251',
			'greek' => 'iso-8859-7',
			'greek' => 'Windows-1253',
			'ujis' => 'x-euc-jp',
			'eucjpms' => 'iso-2022-jp',
			'sjis' => 'shift_jis',
			'euckr' => 'ISO-2022-KR',
			'euckr' => 'ks_c_5601',
			'hebrew' => 'dos-862',
			'hebrew' => 'Windows-1255',
			'hebrew' => 'iso-8859-8',
			'tis620' => 'Windows-874',
			'latin5' => 'iso-8859-9',
			'latin5' => 'Windows-1254',
			'utf8' => 'Windows-1258',
			'utf8mb4' => 'UTF-8',
		];

		return "<charEncoding>{$appEncoding[$row[0]]}</charEncoding>" .
			"<noMySQLEncoding>False</noMySQLEncoding>" .
			($row[0] == 'utf8mb4' ? '<charEncodingMySQLMB4>True</charEncodingMySQLMB4>' : '');
	}

	private function setError($msg) {
		$this->error = $msg;
		return false;
	}

	private function connectToDb() {
		$link = @mysqli_connect(
			$this->dbCredentials['dbServer'],
			$this->dbCredentials['dbUser'],
			$this->dbCredentials['dbPass'],
			$this->dbCredentials['dbName']
		);

		if(!$link)
			$this->askForCredentialsAndExit([
				'error' => 'Error al conectarse al servidor de la base de datos: ' . mysqli_connect_error()
			]);

		return $link;
	}

	private function failAndDie() {
		ob_start(); ?>
		<div class="danger">
			<?php echo $this->error; ?>
			<button type="button" onclick="history.go(-1);">&#9664; Intentar otra vez</button>
		</div>
		<?php
		die(
			ob_get_clean() . 
			$this->copyrightFooter() . 
			$this->cssStyles()
		);
	}

	private function getDbCredentials() {
		$missing = [];
		if(empty($_REQUEST['dbServer'])) $missing['dbServer'] = true;
		if(empty($_REQUEST['dbUser'])) $missing['dbUser'] = true;
		if(empty($_REQUEST['dbName'])) $missing['dbName'] = true;
		if(empty($_REQUEST['dbPass'])) $_REQUEST['dbPass'] = ''; // handle the case of empty password

		if(count($missing))
			$this->askForCredentialsAndExit($missing);

		return $_REQUEST;
	}

	private function insecureConnectionWarning() {
		if(!empty($_SERVER['HTTPS']) || $_SERVER['SERVER_NAME'] == 'localhost')
			return '';

		ob_start(); ?>
		<div class="danger">
			Esta página no se sirve a través de una conexión segura. Es peligroso continuar
                        porque las credenciales de su base de datos se enviarán al servidor sin cifrar y
                        potencialmente podría ser robado.<br>
			puede Probar
			<a href="<?php echo "https://{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}"; ?>">cargando la versión segura de esta página</a>
			si su servidor lo admite, o continúe bajo su propio riesgo.
		</div>
		<?php return ob_get_clean();
	}

	private function askForCredentialsAndExit($missing = []) {
		if(count($missing) == 3) $missing = [];

		ob_start(); ?>
			</p><br><br> <h1> Importar Base de datos MySQL con</h1>
			<p>Utilice esta herramienta para importar la definición de su base de datos MySQL como un archivo de proyecto AXP que se puede abrir en <a href="index.php">SQL</a>.</p>

			<?php echo $this->insecureConnectionWarning(); ?>

			<?php if(!empty($missing['error'])) { ?>
				<div class="danger"><?php echo $missing['error']; ?></div>
			<?php } ?>
 
			<form method="POST" action="<?php echo basename(__FILE__); ?>">
				<div>
					<label for="dbServer">Servidor base de datos</label>
					<input name="dbServer" id="dbServer" value="localhost" autofocus="" required>
					<?php if(!empty($missing['dbServer'])) { ?>
						<div class="danger" id="dbServer-error">Invalid database server provided.</div>
					<?php } ?>
				</div>
				<div>
					<label for="dbName">Nombre de la base de datos</label>
					<input name="dbName" id="dbName" required>
					<?php if(!empty($missing['dbName'])) { ?>
						<div class="danger" id="dbName-error">Invalid database name provided.</div>
					<?php } ?>
				</div>
				<div>
					<label for="dbUser">Nombre de usuario de la base de datos</label>
					<input name="dbUser" id="dbUser" required>
					<?php if(!empty($missing['dbUser'])) { ?>
						<div class="danger" id="dbUser-error">Invalid database username provided.</div>
					<?php } ?>
				</div>
				<div>
					<label for="dbPass">Contraseña de la base de datos</label>
					<input type="password" name="dbPass" id="dbPass">
				</div>
				<div><button type="submit">Continue &#9654;</button></div>
			</form>

			<script>
				// hide error when related input changes
				const inputs = ['dbServer', 'dbName', 'dbUser'];
				for(let inp of inputs) {
					if(document.querySelector(`#${inp}-error`) === null) continue;
						
					document.querySelector(`#${inp}`).addEventListener('keyup', function() {
						if(this.value.length > 0)
							document.querySelector(`#${inp}-error`).classList.add('hidden');
					});
				}
			</script>

			<?php echo $this->copyrightFooter(); ?>
			<?php echo $this->cssStyles(); ?>

		<?php
		die(ob_get_clean());
	}

	private function copyrightFooter() {
		ob_start(); ?>
		<div class="copyright">
			Copyright &copy; <?php echo date('Y'); ?>,
			<a href="https://www.mysql.com">SQL Software</a>.
		</div>
		<?php return ob_get_clean();
	}

	private function cssStyles() {
		ob_start(); ?>
               <title>MySQL App Database Asistente de importación</title>
		<style>
			body {
				width: 100%;
				max-width: 40em;
				margin: 1.5em auto;
				font-family: sans-serif;
				line-height: 1.65em;
				padding-top: 97px;
				background-image: url("data:image/png;base64,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");
				background-repeat: no-repeat;
				background-position-x: center;
				background-position-y: 27px;
			}
			.danger {
				color: red;
				background-color: #FFECEC;
				padding: 0.5em 1em;
				border: solid 1px red;
				margin-bottom: 1em;
			}
			label {
				display: block;
				font-weight: bold;
				margin-top: 1em;
			}
			input {
				width: 100%;
				font-size: 2em;
				font-family: monospace;
				color: #444;
			}
			input:focus {
				color: #000;
				border: solid 2px;
			}
			button {
				font-size: 1.25rem;
				font-weight: bold;
				margin: 1rem auto;
				display: block;
				min-width: 8rem;
				padding: 0.4rem 3rem;
			}
			ul.tables-list {
				max-height: 50vh;
				overflow-y: auto;
				border: dotted 1px #999;
				background-color: #f9f9f9;
				list-style: none;
			}
			ul.tables-list li:before {
				content: '\2714\0020';
			}
			.copyright {
				text-align: center;
				border-top: 1px solid #999;
				padding-top: 0.5rem;
				font-size: 0.8rem;
			}
			.hidden { display: none; }
			a[download] {
				font-weight: bold;
				text-align: center;
				display: block;
				text-decoration: none;
				padding: 1em;
				border: solid 1px silver;
				border-radius: 6px;
				background-color: #ddd;
			}
			a[download]:hover {
				background-color: #eee;
			}
		</style>
		<?php return ob_get_clean();
	}

	private function dataTypeAppIndex($dt) {
		$dataType = [
			'DUMMY_INDEX_BECAUSE_NO_ZERO!',
			'tinyint',
			'smallint',
			'mediumint',
			'int',
			'bigint',
			'float',
			'double',
			'decimal',
			'date',
			'datetime',
			'timestamp',
			'time',
			'year',
			'char',
			'varchar',
			'tinyblob',
			'tinytext',
			'text',
			'blob',
			'mediumblob',
			'mediumtext',
			'longblob',
			'longtext',
		];

		return array_search(strtolower($dt), $dataType) | 0;
	}

	private function getAppTableNames() {
		$tables = [];

		if(!$res = @mysqli_query($this->link, "SHOW TABLES"))
			$this->askForCredentialsAndExit([
				'error' => "Error while listing tables of the database: " . mysqli_error($this->link)
			]);

		while($row = mysqli_fetch_row($res))
			// excluded tables
			if(!preg_match('/^(membership_|appgini_)/i', $row[0]))
				$tables[] = $row[0];

		return $tables;
	}

	private function caption($name) {
		return str_replace(['_', '-'], ' ', ucfirst($name));
	}

	private function getTableFieldsXml($tn) {
		if(!$res = mysqli_query($this->link, "SHOW FIELDS FROM `$tn`"))
			return '';

		/*
		 Expected row:
		 	['Field', 'Type', 'Null', 'Key', 'Default', 'Extra']
		 */

		$axp = '';
		$i = 0;
		while($row = mysqli_fetch_assoc($res)) {
			$m = [];
			if(!preg_match('/^(\w+)(\((\d+)(,(\d+))?\))?/', $row['Type'], $m))
				continue;

			$dtUnsigned = (strpos($row['Type'], ' unsigned') ? 'True' : 'False');
			$dtZeroFill = (strpos($row['Type'], ' zerofill') ? 'True' : 'False');
			$dtType = $m[1];
			$dtLength = $m[3] ?? '';
			$dtPrecision = $m[5] ?? '';
			$dtTextArea = (stripos($dtType, 'text') !== false ? 'True' : 'False');
			
			$notNull = (($row['Null'] == '' || $row['Null'] == 'YES') ? "False" : "True");
			$primaryKey = (strtoupper($row['Key']) == 'PRI' ? "True" : "False");
			$unique = (strtoupper($row['Key']) == 'UNI' ? "True" : "False");
			$autoIncrement = (strtolower($row['Extra']) == 'auto_increment' ? "True" : "False");
			$readOnly = $autoIncrement;

			$axp .= implode('', [
				'<field>',
				'<caption><![CDATA[' . $this->caption($row['Field']) . ']]></caption>',
				'<description><![CDATA[]]></description>',
				"<name>{$row['Field']}</name>",
				'<dataType>' . $this->dataTypeAppIndex($dtType) . '</dataType>',
				"<length>$dtLength</length>",
				"<precision>$dtPrecision</precision>",
				"<autoIncrement>$autoIncrement</autoIncrement>",
				'<binary>False</binary>',
				"<notNull>$notNull</notNull>",
				"<primaryKey>$primaryKey</primaryKey>",
				"<unique>$unique</unique>",
				"<unsigned>$dtUnsigned</unsigned>",
				'<showColumnSum>False</showColumnSum>',
				"<zeroFill>$dtZeroFill</zeroFill>",
				"<default><![CDATA[{$row['Default']}]]></default>",
				'<columnWidth>150</columnWidth>',
				'<forcedWidth>False</forcedWidth>',
				'<maxLengthInTableView>0</maxLengthInTableView>',
				'<allowImageUpload>False</allowImageUpload>',
				"<readOnly>$readOnly</readOnly>",
				"<textarea>$dtTextArea</textarea>",
				'<htmlarea>False</htmlarea>',
				'<linkBehavior>0</linkBehavior>',
				'<linkDisplay>0</linkDisplay>',
				'<linkDisplayField></linkDisplayField>',
				'<checkBox>False</checkBox>',
				'<dataFormat><![CDATA[0]]></dataFormat>',
				'<filterBy></filterBy>',
				'<technicalDocumentation><![CDATA[' . implode(' ', $row) . ']]></technicalDocumentation>',
				"<index>$i</index>",
				'</field>',
			]);

			$i++;
		}

		return $axp;
	}

	private function saveAndDownloadAxp() {
		$filename = $this->dbCredentials['dbName'] . '.axp';
		if(!@file_put_contents(__DIR__ . '/' . $filename, $this->axp))
			$this->askForCredentialsAndExit([
				'error' => "No se pudo guardar el archivo del proyecto. Cambie la carpeta donde cargó este script a 777 y vuelva a intentarlo."
			]);

		?>
			</p><br><br><h1>MySQL App Database <br>Asistente de importación</h1>
		
			<p>Importó con éxito las siguientes tablas:
				<ul class="tables-list">
					<li> <?php echo implode('</li><li> ', $this->tables); ?></li>
				</ul>
			</p>
		
			<h3><a href="<?php echo urlencode($filename); ?>" download>Descarga el archivo MySQL del proyecto</a></h3>
		
			<p>Después de descargar el archivo, puede hacer doble clic en él para abrirlo en Tú editor SQL favorito.</p>
			<div style="text-align: right;">
				<a href="<?php echo basename(__FILE__); ?>">Importar otra base de datos MySQL</a>
			</div>
		<?php
		echo $this->copyrightFooter();
		echo $this->cssStyles();
	}
}