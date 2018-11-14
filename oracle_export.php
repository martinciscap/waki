<?php
$conn = oci_connect('user', 'pass', 'ip/ORCL', 'AL32UTF8');
if (!$conn) {
    $e = oci_error();
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
}

if ($_POST) {    

    if (isset($_POST['export_table'])) {        
        $table = $_POST['export_table'];
        $columns = $_POST['columns'];        
        $id = intval($_POST['id']);
        $len = count($columns);
        $columns_str = implode(',', $columns);        

        $stid = oci_parse($conn, "SELECT * FROM $table");
        oci_execute($stid);
        $table_with_data = FALSE;
        while (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
            $table_with_data = TRUE;
            break;
        }

        if ($table_with_data == FALSE) {
            exit("La tabla $table no tiene data");
        }

        $stid = oci_parse($conn, "SELECT ID FROM $table");
        oci_execute($stid);
        $table_with_id = FALSE;
        while (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
            $table_with_id = TRUE;
            break;
        }

        $table = strtoupper($table);
        if ($table_with_id == TRUE) {

            $stid = oci_parse($conn, "SELECT * FROM $table WHERE ID>$id ORDER BY ID ASC");
            oci_execute($stid);                        
            while (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
                $string = "INSERT INTO $table ($columns_str) VALUES (";

                for ($i = 0; $i < $len; $i++) {
                    $col = strtoupper($columns[$i]);
                    $string .= "'$row[$col]',";
                }            
                $string = substr($string, 0, -1);
                $string .= ");";

                $id = $row['ID'];
                echo json_encode(array('string' => $string, 'id' => $id));
                break;
            }
            exit;            
        } else {
            //exit("La tabla $table no tiene id");        
            $stid = oci_parse($conn, "SELECT * FROM $table");
            oci_execute($stid);            
            $string = '';
            while (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
                $string .= "INSERT INTO $table ($columns_str) VALUES (";

                for ($i = 0; $i < $len; $i++) {
                    $col = $columns[$i];
                    $string .= utf8_encode("'$row[$col]',");
                }            
                $string = substr($string, 0, -1);
                $string .= ");";                                                
            }
            echo json_encode(array('string' => $string, 'id' => -1));
            exit;               
        }        
    }

    if (isset($_POST['table'])) {
        $table = strtoupper($_POST['table']);
        $convertToLower = ($_POST['convertToLower'] === 'true') ? TRUE : FALSE;        

        $stid = oci_parse($conn, "SELECT COLUMN_NAME,NULLABLE,DATA_TYPE,DATA_LENGTH FROM ALL_TAB_COLUMNS WHERE TABLE_NAME='$table'");
        oci_execute($stid);
        $results = FALSE;
        if ($convertToLower) {
            $table = strtolower($_POST['table']);
        } else {
            $table = strtoupper($_POST['table']);
        }
        $string = "CREATE TABLE $table (";
        while (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
            $results = TRUE;
            if ($convertToLower) {
                $columnName = strtolower($row['COLUMN_NAME']);                
            } else {
                $columnName = strtoupper($row['COLUMN_NAME']);                
            }            
            $dataType = $row['DATA_TYPE'];
            $dataLen = $row['DATA_LENGTH'];

            if ($dataType == 'VARCHAR2') {
                $dataType = "VARCHAR($dataLen)";
            }            

            if ($columnName == 'ID') {
                $string .= "ID NUMBER GENERATED ALWAYS AS IDENTITY, ";
            } else {
                $string .=  "$columnName $dataType, ";
            }
        }
        $string = substr($string, 0, -2) . ');';
        echo $string;
        exit;        
    }    
}

if ($_GET) {
    if (isset($_GET['columns'])) {
        $table = $_GET['columns'];

        $stid = oci_parse($conn, "SELECT COLUMN_NAME FROM ALL_TAB_COLUMNS where TABLE_NAME='$table'");
        oci_execute($stid);
        $table = strtoupper($table);            
        $columns = array();        
        while (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
            $col_name = strtoupper($row['COLUMN_NAME']);
            if ($col_name != 'ID') {
                $columns[] = $col_name;
            }            
        }        
        echo json_encode($columns);
        exit;
    }
}

/*$stid = oci_parse($conn, "SELECT  column_name, data_type, data_length FROM all_tab_columns where table_name = 'BANCOS'");
    oci_execute($stid);

    while (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
        // Usar nombres de columna en mayúsculas para los índices del array asociativo
        echo $row[0] . " y " . $row['DEPARTMENT_ID']   . " son lo mismo<br>\n";
        echo $row[1] . " y " . $row['DEPARTMENT_NAME'] . " son lo mismo<br>\n";
    }

    //oci_free_statement($stid);
    //oci_close($conn);*/

$stid = oci_parse($conn, "SELECT TABLE_NAME FROM USER_TABLES ORDER BY TABLE_NAME");
oci_execute($stid);
$tables = '';
while (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
    $tables .= "<option>$row[TABLE_NAME]</option>";
}

echo "<select id='table'>$tables</select><br>";
echo "<label><input type='checkbox' id='convertToLower'> Convertir tablas y columnas a minusculas</label><br>";
echo "<button type='button' id='export'>Exportar Estructura</button><hr>";
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
        var convertToLower = $('#convertToLower').is(':checked');
        $.ajax({
            type: 'POST',
            cache: false,
            data: {
                table: table,
                convertToLower: convertToLower,
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
                    index: 0,
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
                    $("audio#sonidito")[0].currentTime = 0;
					$("audio#sonidito")[0].play();
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

<audio id="sonidito" > <source src="sfx/success.wav"       type="audio/wav"></audio>