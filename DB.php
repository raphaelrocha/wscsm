
<?php
class DB {

	/*
	Retorna o objeto de conexão com o banco de dados.
	*/
	function getConn(){
		$host = 'localhost';
		$db = 'bdcsm';
		$user = 'root';
		$passwd = '';
		$conn = new mysqli($host,$user,$passwd,$db);
		if ($conn->connect_error) {
		    die("Connection failed: " . $conn->connect_error);
		}
		if (!$conn->set_charset("utf8")) {
		    printf("Error loading character set utf8: %s\n", $conn->error);
		} 
		return $conn;
	}

	/*
	Cada tupla, ou linha, é um array de valores
	Retorna um array, com todos os arrays de linhas
	*/
	public static function queryAll($sql){
		$conn = self::getConn();
		$result = $conn->query($sql);

		if($result){
			$array_return = array();
			while ($row = mysqli_fetch_assoc($result)) {
		        array_push($array_return, $row);
		    }
		    return $array_return;
		}else{
			return false;
		}
	}

	/*
	Cada tupla, ou linha, é um array de valores
	Retorna uma linha apenas
	*/
	public static function queryOne($sql){
		$conn = self::getConn();
		$result = $conn->query($sql);

		if($result){
			$array_return = array();
			while ($row = mysqli_fetch_assoc($result)) {
		        return $row;
		    }
		}else{
			return false;
		}
	}

	/*
	Executa a query, e retona "true" em caso de sucesso, e retorna o objeto "$conn" em caso de falha
	Com o objeto "$conn" você pode manipular as mensagens e códigos de erro nas funções. 
	*/
	public static function execute($sql){
		$conn = self::getConn();
		$result = $conn->query($sql);
		if($result){
			return $result;
		}else{
			return $conn;
		}	
	}
}


