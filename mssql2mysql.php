<?php
$con = mssql_connect('ip', 'user', 'pass');
mssql_select_db('dbname', $con);

if (!$con) {
	die('Hay algún problema con la conexión a Microsoft SQL<br>');
}

if ($_POST) {

    if (isset($_POST['export_table'])) {        
        $table = $_POST['export_table'];
        $columns = $_POST['columns'];        
        $id = intval($_POST['id']);
        $len = count($columns);
        $columns_str = implode(',', $columns);

        $sql = "SELECT TOP 1 * FROM $table";
        $res = mssql_query($sql, $con);
        if (! mssql_num_rows($res) > 0) {
            exit("La tabla $table no tiene data");
        }

        $sql = "SELECT TOP 1 id FROM $table";
        $res = @mssql_query($sql, $con);
        if ($res !== FALSE && mssql_num_rows($res) > 0) {
            $sql = "SELECT TOP 1 * FROM $table WHERE id>$id ORDER BY id ASC";
            $res = mssql_query($sql, $con);
            $string = '';
            if (mssql_num_rows($res) > 0) {            
                $string = "INSERT INTO $table ($columns_str) VALUES (";
                //while ($row = mssql_fetch_array($res)) {}
                $row = mssql_fetch_array($res);

                for ($i = 0; $i < $len; $i++) {
                    $col = $columns[$i];
                    $string .= "'$row[$col]',";
                }            
                $string = substr($string, 0, -1);
                $string .= ");";

                $id = $row['id'];
                echo json_encode(array('string' => $string, 'id' => $id));
            }           
            exit;               
        } else {
            //exit("La tabla $table no tiene id");
            $sql = "SELECT * FROM $table";
            $res = mssql_query($sql, $con);
            $string = '';
            if (mssql_num_rows($res) > 0) {
                $string = '';
                while ($row = mssql_fetch_array($res)) {
                    $string .= "INSERT INTO $table ($columns_str) VALUES (";
                    for ($i = 0; $i < $len; $i++) {
                        $col = $columns[$i];
                        $string .= utf8_encode("'$row[$col]',");
                    }            
                    $string = substr($string, 0, -1);
                    $string .= ");";                       
                }
            }            
            echo json_encode(array('string' => $string, 'id' => -1));
            exit;               
        }        
    }

    if (isset($_POST['table'])) {
        $table = $_POST['table'];
        $sql = "SELECT COLUMN_NAME,IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$table'";
        $res = mssql_query($sql, $con);
        if (mssql_num_rows($res) > 0) {
            $table = strtolower($table);
            $string = "CREATE TABLE $table (";
            while ($row = mssql_fetch_array($res)) {

                $columnName = strtolower($row['COLUMN_NAME']);                
                $dataType = $row['DATA_TYPE'];

                if ($dataType == 'bigint' || $dataType == 'int' || $dataType == 'numeric' || $dataType == 'tinyint') {
                    $dataType = 'INTEGER';
                } else if ($dataType == 'varchar' || $dataType == 'varbinary' || $dataType == 'nvarchar') {
                    $dataType = "VARCHAR($row[CHARACTER_MAXIMUM_LENGTH])";
                } else if ($dataType == 'decimal' || $dataType == 'float') {
                    $dataType = "DECIMAL(10,2)";
                } else if ($dataType == 'text') {
                    $dataType = "TEXT";
                } else if ($dataType == 'datetime' || $dataType == 'date') {
                    $dataType = 'DATE';
                } else if ($dataType == 'time') {
                    $dataType = 'TIME';
                } else if ($dataType == 'nchar') {
                    $dataType = 'nchar';
                } else if ($dataType == 'char') {
                    $dataType = 'char';
                } else {
                    exit("Error data type: $dataType");
                }

                if ($columnName == 'ID') {
                    $string .= "int(11) NOT NULL,"; //"ID NUMBER GENERATED ALWAYS AS IDENTITY, ";
                } else {
                    $string .=  "$columnName $dataType, ";
                }
            }
            $string = substr($string, 0, -2) . ')  ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';
            //$string .= ');';

            /*ID NUMBER GENERATED ALWAYS AS IDENTITY,
            FORMA_PAGO INTEGER,
            NOMBRE VARCHAR2(50),
            NOMBRE_FISCAL VARCHAR2(50)
            ) ;*/

            echo $string . " ALTER TABLE $table ADD PRIMARY KEY (`id`); ALTER TABLE `$table` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;";
        }
        exit;
    }    
}

if ($_GET) {
    if (isset($_GET['columns'])) {
        $table = $_GET['columns'];
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$table'";
        $res = mssql_query($sql, $con);
        if (mssql_num_rows($res) > 0) {
            $table = strtolower($table);            
            $columns = array();
            while ($row = mssql_fetch_array($res)) {
                $columns[] = strtolower($row['COLUMN_NAME']);
            }            
        }
        echo json_encode($columns);
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
echo "<button type='button' id='exportData'>Exportar DATA</button><hr>";
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

    $('#exportData').click(function() {
        $('#result').val('');
        var $btn = $(this);
        $btn.prop('disabled', true);
        var table = $('#table').val();
        console.log('exportData', table);
        $.ajax({
            type: 'GET',
            cache: false,
            data: {                
                columns: table,
            },
            success: function(data) {
                var columns = JSON.parse(data);
                console.log({table, columns});
                //$('#result').val(data);
                getQuery({
                    table: table,
                    columns: columns,
                    index: 0, //297497,
                });
            },
            error: function(a, b, c) {
                console.error(a, b, c);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    function getQuery(obj) {
        var index = obj.index,
            table = obj.table;
        $.ajax({
            type: 'POST',
            cache: false,
            data: {
                export_table: table,
                columns: obj.columns,
                id: index,
            },
            success: function(data) {                
                
                if (data.length == 0) {
                    console.warn('se detuvo! en el index:', obj.index);
                    return;
                } else {
                    try {
                        data = JSON.parse(data);                        
                        index = data.id;
                    } catch(err) {
                        console.error(data);
                        return;
                    }
                }
                
                var text = data.string;
                //console.log(text);

                $.ajax({
                    url: 'write.php',
                    type: 'POST',
                    cache: false,
                    data: {
                        table: table,
                        text: text,
                    },
                    success: function(data) {
                        if (data.length > 0) {
                            console.error('write!!', data);
                            return;
                        }                        

                        /*if (index > 90) {
                            console.warn('STOP!');
                            return;
                        }*/
                        console.log('.');
                        if (index != -1) { 
                            getQuery({
                                table: table,
                                columns: obj.columns,
                                index: index,
                            });
                        } else {
                            console.log('done! ;)');
                        }                        
                    }
                });                
            }
        });
    }
});
</script>