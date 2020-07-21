<?php

//Run only if launched via command line
if (php_sapi_name() !== "cli") {
    die("Not in CLI mode");
}

const LIBS_SUBFOLDER = "libs";
const BASE_FOLDER = __DIR__;
const LIBS_FOLDER = BASE_FOLDER . DIRECTORY_SEPARATOR . LIBS_SUBFOLDER;


//LOAD LIBS WITH FUNCTIONS
$files = scandir(LIBS_FOLDER);
foreach($files as $file){
    if(file_exists(LIBS_FOLDER . DIRECTORY_SEPARATOR . $file) && (pathinfo($file, PATHINFO_EXTENSION) == 'php')){
        require(LIBS_FOLDER . DIRECTORY_SEPARATOR . $file);
    }
}

$j = new Cronjobs();
$j->executeActiveJobs();

class Cronjobs {
    private $dsn;
    private $DB;

    function __construct()
    {
        $this->dsn = 'pgsql:host='.$_ENV['DB_HOST'].';port='.$_ENV['DB_PORT'].';dbname='.$_ENV['DB_NAME'];
    }

    function getDBConnection(){
        if ($this->DB) return $this->DB;

        $this->DB = new PDO($this->dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
        return $this->DB;
    }

    function executeActiveJobs(){
        $DB = $this->getDBConnection();

        $month   = date("n");
        $day     = date("j");
        $dow     = date("N"); // Day of week 1-7
        $hour    = date("G");
        $minutes = intval(date("i"));
        $sql = "
        SELECT * FROM cronjobs
        WHERE
            active is true
            AND
            (
                (min = '*')
                OR
                ( ( TRIM(min) ~ '^[0-9]+$' )  AND (min::int = $minutes) )
                OR
                ( ','|| REPLACE(min, ' ', '') ||',' LIKE '%,".$minutes.",%' )
                OR
                (
                    (min LIKE '%*/%') AND ( $minutes % REPLACE(min,'*/','')::int = 0  )
                )
                OR
                (
                    (min LIKE '%-%')
                    AND
                    ( substring(min, 0, position('-' in min))::int <= $minutes   )
                    AND
                    ( substring(min,  position('-' in min)+1)::int >= $minutes  )
                )
            )
            AND
            (
                (hour = '*')
                OR
                ( ( TRIM(hour) ~ '^[0-9]+$' )  AND (hour::int = $hour) )
                OR
                ( ','|| REPLACE(hour, ' ', '') ||',' LIKE '%,".$hour.",%' )
                OR
                (
                    (hour LIKE '%*/%') AND ( $hour % REPLACE(hour,'*/','')::int = 0  )
                )
                OR
                (
                    (hour LIKE '%-%')
                    AND
                    ( substring(hour, 0, position('-' in hour))::int <= $hour   )
                    AND
                    ( substring(hour,  position('-' in hour)+1)::int >= $hour  )
                )
            )
            AND
            (
                (dom = '*')
                OR
                ( ( TRIM(dom) ~ '^[0-9]+$' )  AND (dom::int = $day) )
                OR
                ( ','|| REPLACE(dom, ' ', '') ||',' LIKE '%,".$day.",%' )
                OR
                (
                    (dom LIKE '%*/%') AND ( $day % REPLACE(dom,'*/','')::int = 0  )
                )
                OR
                (
                    (dom LIKE '%-%')
                    AND
                    ( substring(dom, 0, position('-' in dom))::int <= $day   )
                    AND
                    ( substring(dom,  position('-' in dom)+1)::int >= $day  )
                )
            )
            AND
            (
                (mon = '*')
                OR
                ( ( TRIM(mon) ~ '^[0-9]+$' )  AND (mon::int = $month) )
                OR
                ( ','|| REPLACE(mon, ' ', '') ||',' LIKE '%,".$month.",%' )
                OR
                (
                    (mon LIKE '%*/%') AND ( $month % REPLACE(mon,'*/','')::int = 0  )
                )
                OR
                (
                    (mon LIKE '%-%')
                    AND
                    ( substring(mon, 0, position('-' in mon))::int <= $month   )
                    AND
                    ( substring(mon,  position('-' in mon)+1)::int >= $month  )
                )
            )
            AND
            (
                (dow = '*')
                OR
                ( ( TRIM(dow) ~ '^[0-9]+$' )  AND (dow::int = $dow) )
                OR
                ( ','|| REPLACE(dow, ' ', '') ||',' LIKE '%,".$dow.",%' )
                OR
                (
                    (dow LIKE '%-%')
                    AND
                    ( substring(dow, 0, position('-' in dow))::int <= $dow   )
                    AND
                    ( substring(dow,  position('-' in dow)+1)::int >= $dow  )
                )
            )
    ";
        try{
            $result = $DB->query($sql);
            while($data = $result->fetchObject()){
                if( $data->func_name ){
                    $this->execLibFunction($data->func_name, $data->func_args);
                }

                if ( $data->system_script ) {
                    exec($data->system_script);
                }
            }

        } catch(PDOException $e){
            die("Error occurred: ".print_r($e, true));
        }

    }

    function execLibFunction($fname, $fargs){
        if(function_exists($fname)){
            $func = $fname;
            $func($fargs);
        }else{
            echo "\nNo function was executed: $fname \n";
            return false;
        }
    }
}

