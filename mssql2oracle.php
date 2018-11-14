<?php
$con = mssql_connect('ip', 'user', 'pass');
mssql_select_db('dbname', $con);

if (!$con) {
	die('Hay algún problema con la conexión a Microsoft SQL<br>');
}

if ($_POST) {
    if (isset($_POST['table'])) {
        $table = $_POST['table'];
        $sql = "SELECT COLUMN_NAME,IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$table'";
        $res = mssql_query($sql, $con);
        if (mssql_num_rows($res) > 0) {
            $table = strtoupper($table);
            $string = "CREATE TABLE $table (";
            while ($row = mssql_fetch_array($res)) {

                $columnName = strtoupper($row['COLUMN_NAME']);                
                $dataType = $row['DATA_TYPE'];

                if ($dataType == 'bigint' || $dataType == 'int' || $dataType == 'numeric') {
                    $dataType = 'INTEGER';
                } else if ($dataType == 'varchar') {
                    $dataType = "VARCHAR2($row[CHARACTER_MAXIMUM_LENGTH])";
                } else if ($dataType == 'decimal') {
                    $dataType = "DECIMAL(10,2)";
                } else if ($dataType == 'text') {
                    $dataType = "VARCHAR2(250)";
                } else if ($dataType == 'datetime') {
                    $dataType = 'DATE';
                }

                if ($columnName == 'ID') {
                    $string .= "ID NUMBER GENERATED ALWAYS AS IDENTITY, ";
                } else {
                    $string .=  "$columnName $dataType, ";
                }
            }
            $string = substr($string, 0, -2) . ');';
            //$string .= ');';

            /*ID NUMBER GENERATED ALWAYS AS IDENTITY,
            FORMA_PAGO INTEGER,
            NOMBRE VARCHAR2(50),
            NOMBRE_FISCAL VARCHAR2(50)
            ) ;*/

            echo $string;
        }
        exit;
    }    
}

$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME";
$res = mssql_query($sql, $con);
if (mssql_num_rows($res) > 0) {
    $tables = '';    
    while ($row = mssql_fetch_array($res)) {
        $tables .= "<option>$row[TABLE_NAME]</option>";
    }    
}

echo "<select id='table'>$tables</select><br>";
echo "<button type='button' id='export'>Exportar</button><hr>";
echo "<textarea id='result' style='width:100%;height:200px;'></textarea>";
?>
<script
  src="http://code.jquery.com/jquery-1.12.4.min.js"
  integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ="
  crossorigin="anonymous"></script>
<script>
$(function() {
    $('#export').click(function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        var table = $('#table').val();
        $.ajax({
            type: 'POST',
            cache: false,
            data: {
                table: table,
            },
            success: function(data) {
                console.log({data});
                $('#result').val(data);
            },
            error: function(a, b, c) {
                console.error(a, b, c);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>