<?php

require 'Zend/Config.php';
require 'Zend/Db.php';
require 'config.php';

$config = new Zend_Config($Config);

$db = Zend_Db::factory('Pdo_Pgsql', array(
    'host'     => $config->db->dbhost,
    'username' => $config->db->dbuser,
    'password' => $config->db->dbpass,
    'dbname'   => $config->db->dbname,
));

// get schemas from pg_catalog system table
$sql = "SELECT 
			oid, nspname 
		FROM pg_catalog.pg_namespace
		WHERE 
			nspname != 'pg_catalog'
		AND
			nspname != 'pg_toast'
		AND
			nspname != 'pg_temp_1'
		AND
			nspname != 'pg_toast_temp_1'
		AND
			nspname != 'information_schema'
		AND
			nspname != 'public'
		";

$schema = $db->fetchAll($sql);

//print_r($schema);

$schemas_oid = array();
$schemas = array();

for ( $i=0 ; $i < count($schema) ; $i++ ) {
	array_push($schemas_oid,$schema[$i]['oid']);
	array_push($schemas,$schema[$i]['nspname']);
}

//print_r($schemas);

// get postgresql all fieldtype
$sql = 'SELECT oid, typname FROM pg_catalog.pg_type';
$field = $db->fetchAll($sql);

$fields = array();

for ( $i=0 ; $i < count($field) ; $i++ ) {
	$fields[$field[$i]['oid']] = $field[$i]['typname'];
}

// print_r($fields);

// get all function and field
$sql = "SELECT 
			proc.proname,
			proc.pronamespace,
			proc.pronargs,
			proc.proargtypes,
			proc.proargnames,
			schema.nspname
		FROM pg_catalog.pg_proc AS proc
		LEFT JOIN pg_catalog.pg_namespace AS schema ON schema.oid = proc.pronamespace
		WHERE 
			schema.nspname != 'pg_catalog'
		AND
			schema.nspname != 'pg_toast'
		AND
			schema.nspname != 'pg_temp_1'
		AND
			schema.nspname != 'pg_toast_temp_1'
		AND
			schema.nspname != 'information_schema'
		AND
			schema.nspname != 'public'
		";

$proc = $db->fetchAll($sql);

//print_r($proc);

$field_type = array();

// combination all variable to array
for ( $proc_arrid=0 ; $proc_arrid < count($proc) ; $proc_arrid++ ) {
	$procname = $proc[$proc_arrid]['proname'].'_'.$proc[$proc_arrid]['pronargs'];
	$field_split_type = explode(" ", $proc[$proc_arrid]['proargtypes']);
	for ( $fieldsplit_arrid=0 ; $fieldsplit_arrid < count($field_split_type) ; $fieldsplit_arrid++ ) {
		array_push($field_type, $fields[$field_split_type[$fieldsplit_arrid]]);
	}
	//print_r($field_type);
	$field_name = pg_array_parse($proc[$proc_arrid]['proargnames']);

	for ( $fieldname_arrid=0 ; $fieldname_arrid < count($field_name) ; $fieldname_arrid++ ) {
		$g_field[$field_name[$fieldname_arrid]] = array(
											'type' => $field_type[$fieldname_arrid],
											'sort' => ((int)$fieldname_arrid + 1),
											'default' => '',
										);
	}
	$g_final[$proc[$proc_arrid]['nspname']][$procname] = $g_field;
	$g_field = array();
}

// final, write php array to file
array2file($g_final,$config->writefile);



 
/*
 * convert postgresql array type(ex: text[], varchar[])
 * to php array variable
 */
function pg_array_parse($array, $asText = true) {
    $s = $array;
    if ($asText) {
        $s = str_replace("{", "array('", $s);
        $s = str_replace("}", "')", $s);   
        $s = str_replace(",", "','", $s);   
    } else {
        $s = str_replace("{", "array(", $s);
        $s = str_replace("}", ")", $s);
    }
	$ss = "\$retval = $s;";
    eval($ss);
	return $retval;
}

/*
 * convert php array variable to file
 */
function array2file($array, $filename) {
	$temp = '<?php
class ProcMap
{
    private static $_procmap = '.var_export($array, TRUE).';

    public static function getProcAll()
    {
        return self::$_procmap;
    }

    public static function getProcFromSchema($schema_name)
    {
        return self::$_procmap[$schema_name];
    }

}
?>';
	file_exists($filename) or touch($filename);
	file_put_contents($filename, $temp);
}

?>
