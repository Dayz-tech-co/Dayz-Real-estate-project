<?php



namespace Config;






abstract class DB_Connect

{ 



    /**

     * Get the database connection

     *

     * @return mixed

     */

    protected static function getDB()

    {

        static $db = null;



        if ($db === null) {

            if($_ENV['LIVE_OR_LOCAL']==0){

                $server=  $_ENV['DB_HOST'];

                $username= $_ENV['TEST_DB_USERNAME'];

                $password= $_ENV['TEST_DB_PASSWORD'];

                $dbname=  $_ENV['TEST_DB_DATABASE'];

            }else{

                $server=  $_ENV['DB_HOST'];

                $username= $_ENV['DB_USERNAME'];

                $password= $_ENV['DB_PASSWORD'];

                $dbname=  $_ENV['DB_DATABASE'];

            }



            $db= mysqli_connect($server, $username, $password, $dbname);

            // Check if connection was successful

            if (mysqli_connect_errno()) {

                die("Failed to connect to database: " . mysqli_connect_error());

            }

            $db->set_charset("utf8mb4");



        }



        return $db;

    }

}

