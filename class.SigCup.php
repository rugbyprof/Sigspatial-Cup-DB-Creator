<?php
//---------------------------------------------------------------------
//
// Name:    Terry Griffin
//
// Purpose: Takes all of the files downloaded from Sigspatial Cup and loads them into a 
//			MySQL database. Has nothing directly to do with a solution, but makes it
//			easy to visualize some of our ideas using "OpenLayers".  The visualization
//			portion is not included.
//
//			There's no configuration for paths and such. You can change the code based
//			on where you extracted the files.
//			
//			No primary keys or indexes included in the table creation. That's up to you also.
//
// License: MIT
//
// Copyright (c) 2012
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), 
// to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, 
// and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS 
// IN THE SOFTWARE.
//---------------------------------------------------------------------

//Usage:
//
// $GPS = new LoadGpsData();
// $GPS->CreateDatabase();
// $Gps->LoadTables();
// 

class LoadGpsData{

	var $connect;
	var $db;
	
	
   //-----------------------------------------------------------------
   // Construcor: links to the database
   // @Param: void
   // @Returns: void
   //-----------------------------------------------------------------	
	function __construct(){
		$this->connect = mysql_connect('localhost','youruser','yourpass');
		$this->db = mysql_select_db('yourdb');
	}
	
   //-----------------------------------------------------------------
   // CreateDatabase: Creates all the tables in the database, if they don't already exist
   // @Param: void
   // @Returns: void
   //-----------------------------------------------------------------
	public function CreateDatabase(){
		//WA_Edges.txt
		//<EdgeId> <from> <to> <cost>
		//2 0 535410 523
		if(!$this->TableExists('Edges')){
			mysql_query("CREATE TABLE `Edges` (`EdgeId` INT( 9 ) NOT NULL,`from` INT( 9 ) NOT NULL ,`to` INT( 9 ) NOT NULL ,`cost` INT( 9 ) NOT NULL,`Name` VARCHAR(128),`Type` VARCHAR(64),`Length` INT(9)");
		}

		//WA_Nodes.txt
		//<NodeId> <lat> <long>
		//0 47.2964240 -122.2445086
		if(!$this->TableExists('Nodes')){
			mysql_query("CREATE TABLE  `Nodes` (`NodeId` INT( 9 ) NOT NULL ,`lat` DOUBLE( 14, 9 ) NOT NULL ,`long` DOUBLE( 14, 9 ) NOT NULL");
		}
		
		//WA_EdgeGeometry.txt
		//<EdgeId>^<Name>^<Type>^<Length>^<Lat_1>^<Lon_1>^...^<Lat_n>^<Lon_n>
		//0^Supermall Way^residential^18.076637118384^47.2964240^-122.2445086^47.2964237^-122.2442696
		if(!$this->TableExists('EdgeGeometry')){
			mysql_query("CREATE TABLE `EdgeGeometry` (`EdgeId` INT( 9 ) NOT NULL ,`lat` DOUBLE(14,9),`long` DOUBLE (14,9)");
		}
		
		//Input_Files
		//<Time>,<Latitude>,<Longitude>
		if(!$this->TableExists('Input_Files')){
			mysql_query("CREATE TABLE  `QuadTree`.`Input_Files` (`FileName` VARCHAR( 32 ) NOT NULL ,`Time` INT( 5 ) NOT NULL ,`Lat` DOUBLE( 14, 9 ) NOT NULL ,`LON` DOUBLE( 14, 9 ) NOT NULL)");
		}
		
		//input_files
		//<Time>,<Latitude>,<Longitude>
		if(!$this->TableExists('Output_Files')){
			mysql_query("CREATE TABLE  `QuadTree`.`Output_Files` (`FileName` VARCHAR( 32 ) NOT NULL ,`Time` INT( 5 ) NOT NULL ,`EdgeId` INT( 7 ) NOT NULL ,`Confidence` DECIMAL( 3, 2 ) NOT NULL)");
		}
		
		//TrainingData combined
		//<FileName>,<Time>,<Latitude>,<Longitude>,<EdgeId>,<Confidence>,<Gap>
		if(!$this->TableExists('Training_Data')){
			mysql_query("CREATE TABLE  `QuadTree`.`Training_Data` (`FileName` VARCHAR( 32 ) NOT NULL ,`Time` INT( 7 ) NOT NULL ,`Lat` DOUBLE( 14, 9 ) NOT NULL ,`Lon` DOUBLE( 14, 9 ) NOT NULL ,`EdgeId` INT( 7 ) NOT NULL ,`Confidence` DOUBLE( 3, 2 ) NOT NULL ,`Gap` INT( 4 ) NOT NULL)");
		}

	}
	
   //-----------------------------------------------------------------
   // LoadTables: Single method call to load all the tables. 
   // @Param: void
   // @Returns: void
   //-----------------------------------------------------------------
	public function LoadTables(){
		$this->LoadEdgeTable("WA_Edges.txt");
		$this->LoadNodeTable("WA_Nodes.txt");
		$this->LoadEdgeGeometryTable("WA_EdgeGeometry.txt");
		$this->LoadInputFiles("./GisContestTrainingData/input");
		$this->LoadOutputFiles("./GisContestTrainingData/output");
		$this->LoadTrainingData("./GisContestTrainingData/input","./GisContestTrainingData/output");
	}
	
	
   //-----------------------------------------------------------------
   // LoadEdgeGeometryTable: Loads the edge geometry table. 
   // @Param: string: filename to open and read from
   // @Returns: void
   //-----------------------------------------------------------------	
	public function LoadEdgeGeometryTable($filename){
		//WA_EdgeGeometry.txt
		//<EdgeId>^<Name>^<Type>^<Length>^<Lat_1>^<Lon_1>^...^<Lat_n>^<Lon_n>
		//0^Supermall Way^residential^18.076637118384^47.2964240^-122.2445086^47.2964237^-122.2442696

		$fp = fopen($filename,"r");
		while(!feof($fp)){
			$line = fgets($fp);
			$values = explode('^',$line);
			$EdgeId = trim(array_shift($values));
			$Name = trim(array_shift($values));
			$Type = trim(array_shift($values));
			$Length = trim(array_shift($values));
			$result = mysql_query("UPDATE `Edges` SET Name = '{$Name}',Type = '{$Type}', Length='{$Length}' WHERE EdgeId = '{$EdgeId}'");
			for($i=0;$i<sizeof($values);$i+=2){
				$result = mysql_query("INSERT INTO EdgeGeometry VALUES ('{$EdgeId}','{$values[$i]}','{$values[$i+1]}')");
			}
		}

	}
	
   //-----------------------------------------------------------------
   // LoadEdgeTable: Loads the edges table. 
   // @Param: string: filename to open and read from
   // @Returns: void
   //-----------------------------------------------------------------	
	public function LoadEdgeTable($filename){
		//WA_Edges.txt
		//<EdgeId> <from> <to> <cost>
		//2 0 535410 523
		
		$fp = fopen($filename,"r");
		while(!feof($fp)){
			$line = fgets($fp);
			list($EdgeId,$from,$to,$cost) = explode(' ',$line);
			$EdgeId = trim($EdgeId);
			$from=trim($from);
			$to=trim($to);
			$cost=trim($cost);
			$result = mysql_query("INSERT INTO `Edges` VALUES ('{$EdgeId}','{$from}','{$to}','{$cost}','','','')");
		}

	}

   //-----------------------------------------------------------------
   // LoadEdgeTable: Loads the nodes table. 
   // @Param: string: filename to open and read from
   // @Returns: void
   //-----------------------------------------------------------------
	public function LoadNodeTable($filename){
		//WA_Nodes.txt
		//<NodeId> <lat> <long>
		//0 47.2964240 -122.2445086

		if(!$fp = fopen($filename,"r")){
			echo "ERROR: File '{$filename}' Not Opened.\n";
			exit;
		}
		while(!feof($fp)){
			$line = fgets($fp);
			list($NodeId,$lat,$long) = explode(' ',$line);
			$NodeId = trim($NodeId);
			$lat = trim($lat);
			$long = trim($long);
			$result = mysql_query("INSERT INTO `Nodes` VALUES ('{$NodeId}','{$lat}','{$long}')");				
		}
	}
	
   //-----------------------------------------------------------------
   // LoadEdgeTable: Loads the input training data table. 
   // @Param: string: filename to open and read from
   // @Returns: void
   //-----------------------------------------------------------------
	public function LoadInputFiles($path='.'){
		$files = scandir($path);
		array_shift($files);
		array_shift($files);
		foreach($files as $file){
			if(!$fp = fopen("{$path}/{$file}","r")){
				echo "ERROR: File '{$path}/{$file}' Not Opened.\n";
				exit;
			}
			while(!feof($fp)){
				$line = fgets($fp);
				list($time,$lat,$lon) = explode(',',$line);
				$result = mysql_query("INSERT INTO `Input_Files` VALUES('{$file}','{$time}','{$lat}','{$lon}')");
			}
		}
	}

   //-----------------------------------------------------------------
   // LoadEdgeTable: Loads the output training data table. 
   // @Param: string: filename to open and read from
   // @Returns: void
   //-----------------------------------------------------------------
	public function LoadOutputFiles($path='.'){
		$files = scandir($path);
		array_shift($files);
		array_shift($files);
		foreach($files as $file){
			if(!$fp = fopen("{$path}/{$file}","r")){
				echo "ERROR: File '{$path}/{$file}' Not Opened.\n";
				exit;
			}
			while(!feof($fp)){
				$line = fgets($fp);
				list($time,$edgeId,$confidence) = explode(',',$line);
				$result = mysql_query("INSERT INTO `Output_Files` VALUES('{$file}','{$time}','{$edgeId}','{$confidence}')");
			}
		}
	}

   //-----------------------------------------------------------------
   // LoadEdgeTable: Combines both the input and output data into one file.
   // 				Since the output files really just showed the edge a lat,lon
   //				matched to, I put them together in the same table, along with
   //				with a time "gap" column to see the granularity of the gps 
   //				trace.
   // @Param: string: filename to open and read from
   // @Returns: void
   //-----------------------------------------------------------------
	public function LoadTrainingData($InPath='.',$OutPath="."){
		$prev_time=0;
		$time=0;
		$gap=0;
		
		$InputFiles = scandir($InPath);
		$OutputFiles = scandir($OutPath);	
		
		//Remove the '.' and the '..'
		array_shift($InputFiles);
		array_shift($InputFiles);
		
		//Remove the '.' and the '..'
		array_shift($OutputFiles);
		array_shift($OutputFiles);
		
		for($i=0;$i<sizeof($InputFiles);$i++){
			if(!$fpIn = fopen("{$InPath}/{$InputFiles[$i]}","r")){
				echo "ERROR: File '{$InPath}/{$InputFiles[$i]}' Not Opened.\n";
				exit;
			}
			if(!$fpOut = fopen("{$OutPath}/{$OutputFiles[$i]}","r")){
				echo "ERROR: File '{$OutPath}/{$OutputFiles[$i]}' Not Opened.\n";
				exit;
			}
			while(!feof($fpIn)){
				$line1 = fgets($fpIn);
				$line2 = fgets($fpOut);	
				
				list($time,$lat,$lon) = explode(',',$line1);
				list($time,$edgeId,$confidence) = explode(',',$line2);
				
				$gap=$time-$prev_time;
				
				$result = mysql_query("INSERT INTO `Training_Data` VALUES('{$InputFiles[$i]}','{$time}','{$lat}','{$lon}','{$edgeId}','{$confidence}','{$gap}')");
				
				$prev_time=$time;
			}
			$gap=0;
			$prev_time=0;
		}
	}	
	
   //-----------------------------------------------------------------
   // TableExists: Dont create a table that exists 
   // @Param: string: tablename
   // @Returns: bool: table exists=1, otherwise=0
   //-----------------------------------------------------------------
	private function TableExists($tablename){
		$res = mysql_query("show table status like '$tablename'") or die(mysql_error());
		return mysql_num_rows($res) == 1;
	}

}


?>