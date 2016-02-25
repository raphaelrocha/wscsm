<?php
//importe da classe de acesso ao banco de dados.
require 'DB.php';

/*
Todas as funções são chamdas via POST.
Todas os parâmetros de entrada que vem do Android, chegam em um objeto JSON como valor da chave "json" no array $_POST[]
Ex. $_POST{"json"=>"{'id':'1','nome':'joazinho','email':'joaozinho@gmail.com'}"}
*/
header ('Content-type: text/html; charset=UTF-8');
if(function_exists($_GET['f'])) {
	$json = json_decode($_POST["json"]);
   	$_GET['f']($json);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=listarHospitais
Retorno json object
Parametros de entrada 
user_latiude = longitude do dispositivo android que está fazendo a requisição
radius = raio em KM com origem no ponto onde o sispositivo android se encontra
Retorna os hospitais com bairro, cidade, estado, pais encadeados.
Para cada hospital, existe uma lista de avaliações e cada avaliação tem seu usuário remetente, encadeado.

Ex de entreada
{
	"radius": "10",
	"user_latitude": "-3.0068849",
	"user_longitude": "-60.0429712"
}

Ex de retorno
{
	"hospitais": [{
		"id": "28",
		"id_Bairro": "1",
		"endereco": "Estr. Torquarto Tapajós, s\/n, Colô4nia Terra Nova, 69093-415",
		"foto": "abdel.jpg",
		"localizacao": "-2.9979174;-60.0322077",
		"latitude": "-2.9979174",
		"longitude": "-60.0322077",
		"nome": "Hospital Pronto Socorro Rinaldi Abdel Aziz",
		"distancia": "1.5565349752582311",
		"bairro": {
			"id": "1",
			"nome": "Centro",
			"id_Cidade": "1",
			"cidade": {
				"id": "1",
				"data_registro": "2015-12-15 15:16:00",
				"nome": "Manaus",
				"id_Estado": "1",
				"estado": {
					"id": "1",
					"data_registro": "2015-12-15 15:15:00",
					"nome": "Amazonas",
					"id_Pais": "1",
					"pais": {
						"id": "1",
						"data_registro": "2015-12-15 00:00:00",
						"nome": "Brasil"
					}
				}
			}
		},
		"avaliacoes": [{
			"id": "5",
			"comentario": "Teste",
			"nota": "5",
			"dt_registro": "2016-01-08 04:52:10",
			"id_hospital": "28",
			"id_usuario": "1",
			"usuario": {
				"id": "1",
				"cpf": "123.456.789-00",
				"dt_Nascimento": "2000-02-14 00:00:00",
				"dt_Registro": "2000-02-14 00:00:00",
				"email": "teste@teste.com",
				"foto": "1.jpg",
				"nome": "Tony Stark",
				"senha": "123",
				"sexo": "0"
			}
		}]
	}]
}
*/
function listarHospitais($json){
	$array_response = array();
	$eath_radius_in_km = 6371;
	$userLatitude = $json->user_latitude;
	$userLongitude = $json->user_longitude;
	$radiusInKm = $json->radius;

	$sql = "select hospital.*, ($eath_radius_in_km *
			        acos(
			            cos(radians($userLatitude)) *
			            cos(radians(latitude)) *
			            cos(radians($userLongitude) - radians(longitude)) +
			            sin(radians($userLatitude)) *
			            sin(radians(latitude))
			        )) AS distancia from hospital
		HAVING distancia <= $radiusInKm
		ORDER BY distancia ASC";
	$tempHospitais = DB::queryAll($sql);

	$hospitais = array();
	foreach ($tempHospitais as $hospital) {
		$idBairro = $hospital["id_Bairro"];
		$idHospital = $hospital["id"];
		$sql = "select * from bairro where id = $idBairro";
		$bairro = DB::queryOne($sql);

		$idCidade = $bairro["id_Cidade"];
		$sql = "select * from cidade where id = $idCidade";
		$cidade = DB::queryOne($sql);

		$idEstado = $cidade["id_Estado"];
		$sql = "select * from estado where id = $idEstado";
		$estado = DB::queryOne($sql);

		$idPais = $estado["id_Pais"];
		$sql = "select * from pais where id = $idPais";
		$pais = DB::queryOne($sql);

		$sql = "select * from avalia_hospital where id_hospital = $idHospital";
		$avaliacoes = DB::queryAll($sql);

		$estado["pais"] = $pais;
		$cidade["estado"] = $estado;
		$bairro["cidade"] = $cidade;
		$hospital["bairro"] = $bairro;
		if(sizeof($avaliacoes)>0){
			$tempAv = array();
			foreach ($avaliacoes as $avaliacao) {
				$idUsuario = $avaliacao["id_usuario"];
				$sql = "select * from usuario where id=$idUsuario";
				$usuario = DB::queryOne($sql);
				$avaliacao["usuario"] = $usuario;

				array_push($tempAv, $avaliacao);
			}
			$hospital["avaliacoes"] = $tempAv;
		}
		
		array_push($hospitais, $hospital);
	}

	$array_response["hospitais"]=$hospitais;

    echo json_encode($array_response);
}


/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=buscarHospitais
Retorno json object
Parametros de entrada são os mesmo da função listarHospitais acrescido do da variável "query" que é a string usapada para realizar a busca.

Ex de entrada
{
	"radius": "10",
	"user_latitude": "-3.0068849",
	"user_longitude": "-60.0429712",
	"query": "rinaldi"
}

O retorno também é idêntico ao da função listarHospitais.
*/
function buscarHospitais($json){
	$array_response = array();
	$eath_radius_in_km = 6371;
	$userLatitude = $json->user_latitude;
	$userLongitude = $json->user_longitude;
	$radiusInKm = $json->radius;
	$query = $json->query;

	$sql = "select hospital.*, ($eath_radius_in_km *
			        acos(
			            cos(radians($userLatitude)) *
			            cos(radians(latitude)) *
			            cos(radians($userLongitude) - radians(longitude)) +
			            sin(radians($userLatitude)) *
			            sin(radians(latitude))
			        )) AS distancia from hospital
		join bairro on (hospital.id_Bairro = bairro.id) 
					join cidade on (bairro.id_Cidade = cidade.id)
                    join estado on (cidade.id_Estado = estado.id)
                    join pais on (estado.id_Pais = pais.id)
		where hospital.nome like '%$query%' or
                hospital.endereco like '%$query%' or
                bairro.nome like '%$query%' or
                cidade.nome like '%$query%' or
                estado.nome like '%$query%' or
                pais.nome like '%$query%'
		ORDER BY distancia ASC";
	$tempHospitais = DB::queryAll($sql);

	$hospitais = array();
	foreach ($tempHospitais as $hospital) {
		$idBairro = $hospital["id_Bairro"];
		$idHospital = $hospital["id"];
		$sql = "select * from bairro where id = $idBairro";
		$bairro = DB::queryOne($sql);

		$idCidade = $bairro["id_Cidade"];
		$sql = "select * from cidade where id = $idCidade";
		$cidade = DB::queryOne($sql);

		$idEstado = $cidade["id_Estado"];
		$sql = "select * from estado where id = $idEstado";
		$estado = DB::queryOne($sql);

		$idPais = $estado["id_Pais"];
		$sql = "select * from pais where id = $idPais";
		$pais = DB::queryOne($sql);

		$sql = "select * from avalia_hospital where id_hospital = $idHospital";
		$avaliacoes = DB::queryAll($sql);

		$estado["pais"] = $pais;
		$cidade["estado"] = $estado;
		$bairro["cidade"] = $cidade;
		$hospital["bairro"] = $bairro;
		if(sizeof($avaliacoes)>0){
			$tempAv = array();
			foreach ($avaliacoes as $avaliacao) {
				$idUsuario = $avaliacao["id_usuario"];
				$sql = "select * from usuario where id=$idUsuario";
				$usuario = DB::queryOne($sql);
				$avaliacao["usuario"] = $usuario;

				array_push($tempAv, $avaliacao);
			}
			$hospital["avaliacoes"] = $tempAv;
		}
		array_push($hospitais, $hospital);
	}
	
	$array_response["hospitais"]=$hospitais;

    echo json_encode($array_response);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=login
Retorno json object
Parâmetros de entrada.
email - email do usuário que deseja logar.
senha - senha do usuário que deseja logar.

Ex de entrada valida
{
	"senha": "123",
	"email": "teste@teste.com"
}

Ex de retorno de entrada válida
{
	"success": true,
	"usuario": {
		"id": "1",
		"cpf": "123.456.789-00",
		"dt_Nascimento": "2000-02-14 00:00:00",
		"dt_Registro": "2000-02-14 00:00:00",
		"email": "teste@teste.com",
		"foto": "1.jpg",
		"nome": "Tony Stark",
		"senha": "123",
		"sexo": "0"
	}
}

Ex de retorno para usuario inexistente ou senha incorreta
{"success":false}
*/
function login($json){
	$array_response = array();
	$email = $json->email;
	$senha = $json->senha;

	$sql = "select * from usuario where email = '$email' and senha = '$senha'";
	$usuario = DB::queryOne($sql);

	if($usuario){
		$array_response["success"]=true;
		$array_response["usuario"]=$usuario;
	}else{
		$array_response["success"]=false;
	}
	
    echo json_encode($array_response);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=listarComentariosHospital
Retorno json object
Retorna a lista de todos os comentários de um hospital
Cada comentário vem com seu remetente encadeado.

Parâmetros de entrada
id_hospita = é o id do hospital cujo o usuário deseja visualizar a lista de comentários.

Ex de entrada válida
{"id_hospital":"28"}

Ex de retorno quendo possui um ou mais comentários
{
	"success": true,
	"comentarios": [{
		"id": "9",
		"id_Usuario": "1",
		"id_Hospital": "28",
		"texto": "Teste",
		"dt_Registro": "2016-01-08 04:23:07",
		"usuario": {
			"id": "1",
			"cpf": "123.456.789-00",
			"dt_Nascimento": "2000-02-14 00:00:00",
			"dt_Registro": "2000-02-14 00:00:00",
			"email": "teste@teste.com",
			"foto": "1.jpg",
			"nome": "Tony Stark",
			"senha": "123",
			"sexo": "0"
		}
	}]
}

Ex de retorno quando não possui hospitais
{"success":false}
*/
function listarComentariosHospital($json){
	$array_response = array();
	$idHospital = $json->id_hospital;

	$sql = "select * from comentario where id_Hospital = '$idHospital'";
	$tempComentarios = DB::queryAll($sql);

	if($tempComentarios){
		$comentarios = array();
		foreach ($tempComentarios as $comentario) {
			$idUsuario = $comentario["id_Usuario"];

			$sql = "select * from usuario where id = '$idUsuario'";
			$usuario = DB::queryOne($sql);

			$comentario["usuario"]=$usuario;
			array_push($comentarios, $comentario);
		}

		$array_response["success"]=true;
		$array_response["comentarios"]=$comentarios;
	}else{
		$array_response["success"]=false;
	}
	
    echo json_encode($array_response);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=enviarComentario
Retorno json object
Salva os comentários enviados a um hospital pelo usuário

Parâmetros de entrada
texto = mensagem enviada pelo usuario.
id_usuario = id do usuário retente.
id_hospital = id do hospital destino.

Ex de entrada válida
{
	"texto": "Comentário de teste",
	"id_usuario": "15",
	"id_hospital": "52"
}

Ex de retorno de sucesso
{"success":true}

Ex de retono em caso de falha
{
	"success": false,
	"code": 1452,
	"error": "Cannot add or update a child row: a foreign key constraint fails (`bdcsm`.`comentario`, CONSTRAINT `comentario_ibfk_1` FOREIGN KEY (`id_Usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE)"
}

*/
function enviarComentario($json){
	$array_response = array();
	$idHospital = $json->id_hospital;
	$idUsuario = $json->id_usuario;
	$texto = $json->texto;

	$sql = "insert into comentario (`id_Hospital`, `id_Usuario`, `texto`) values ($idHospital,$idUsuario,'$texto')";
	$salvar = DB::execute($sql);

	if($salvar===true){
		$array_response["success"]=true;
	}else{
		$array_response["success"]=false;
		$array_response["errno"]=$salvar->errno;
		$array_response["error"]=$salvar->error;
	}
	
    echo json_encode($array_response);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=removerComentario
Retorno json object
Remove um comentário qualquer

Parâmetro de entrada
id_comentario = ido comentario seleciona para exclusão

Ex de entrada válida.
{"id_comentario":"17"}

Ex de retorno de sucessoo
{"success":true}

*/
function removerComentario($json){
	$array_response = array();
	$idComentario = $json->id_comentario;

	$sql = "delete from comentario where id=$idComentario";
	$deletar = DB::execute($sql);

	if($deletar===true){
		$array_response["success"]=true;
	}else{
		$array_response["success"]=false;
		$array_response["errno"]=$deletar->errno;
		$array_response["error"]=$deletar->error;
	}
	
    echo json_encode($array_response);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=verificaAvaliacao
Retorno json object
Retorna a avaliação realizada pelo usuário a um hospital

Parâmetros de entrada
id_usuário = id do usuário que fez a avaliação.
id_hospital = id do hospital que recebeu a avaliação.

Ex de entrada válida
{
	"id_usuario": "15",
	"id_hospital": "28"
}

Ex de retorno de sucesso
{
	"success": true,
	"avaliacao": {
		"id": "8",
		"comentario": "",
		"nota": "4",
		"dt_registro": "2016-01-08 16:17:55",
		"id_hospital": "28",
		"id_usuario": "15",
		"usuario": {
			"id": "15",
			"cpf": "00000000000",
			"dt_Nascimento": "0000-00-00 00:00:00",
			"dt_Registro": null,
			"email": "1052369734827642@userfacebook.com",
			"foto": "https:\/\/graph.facebook.com\/1052369734827642\/picture?height=300&width=300&migration_overrides=%7Boctober_2012%3Atrue%7D",
			"nome": "Raphael Rocha",
			"senha": "tweghcewuy98378@hisgad87",
			"sexo": "0"
		}
	}
}

Ex de retorno de erro ou sem avaliação
{"success":false}
*/
function verificaAvaliacao($json){
	$array_response = array();
	$idUsuario = $json->id_usuario;
	$idHospital = $json->id_hospital;

	$sql = "select * from avalia_hospital where id_usuario=$idUsuario and id_hospital = $idHospital";
	$avaliacao = DB::queryOne($sql);

	if($avaliacao){
		$array_response["success"]=true;
		$sql = "select * from usuario where id=$idUsuario";
		$usuario = DB::queryOne($sql);
		$avaliacao["usuario"] = $usuario;
		$array_response["avaliacao"]=$avaliacao;
	}else{
		$array_response["success"]=false;
	}
	
    echo json_encode($array_response);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=avaliar
Retorno json object
Realiza uma avaliação caso o usuário ainda não tenha avaliado, ou faz update na avaliação, caso já exista uma avaliação anterior.

Parâmetros de entrada
id_usuário = id do usuário que faz a avaliação.
id_hospital = id do hospital que recebe a avaliação.
nota = valor de 1 a 5 referente a quantidadde de estrelas selecionadas
texto = mensagem enviada pelo usuário na tela de avaliação

Ex de entrada válida
{
	"texto": "Minha opinião",
	"id_usuario": "15",
	"id_hospital": "13",
	"nota": "4"
}

Ex de retorno de sucesso.
{"success":true}

Ex de retorno de erro.
Obs, "op" varia entre "insert" e "update"
{
	"success": false,
	"errno": 1452,
	"error": "Cannot add or update a child row: a foreign key constraint fails (`bdcsm`.`avalia_hospital`, CONSTRAINT `FK244FE66147364730` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`))",
	"op": "insert"
}
*/
function avaliar($json){
	$array_response = array();
	$idUsuario = $json->id_usuario;
	$idHospital = $json->id_hospital;
	$nota = $json->nota;
	$texto = $json->texto;

	/*
	Na conversa com o Lucas, chegamos a conclusãoq ue este seja o ponto mais adequado para chamar a função de análise de sentimento.
	Neste momento, você envia o texto para a anáise, e a nota resultante você envia para o insert.
	Dessa forma, o comportamento da App não muda.
	*/

	$sql = "select * from avalia_hospital where id_usuario=$idUsuario and id_hospital = $idHospital";
	$avaliacao = DB::queryOne($sql);

	if($avaliacao){
		$idAv = $avaliacao["id"];
		$sql = "update avalia_hospital set nota = '$nota', comentario = '$texto' where id = $idAv";
		$update = DB::execute($sql);
		if($update===true){
			$array_response["success"]=true;
		}else{
			$array_response["success"]=false;
			$array_response["errno"]=$update->errno;
			$array_response["error"]=$update->error;
			$array_response["op"]="update";
		}
	}else{
		$sql = "insert into avalia_hospital (id_usuario, id_hospital, nota, comentario) values ($idUsuario, $idHospital, $nota, '$texto')";
		$salvar = DB::execute($sql);

		if($salvar===true){
			$array_response["success"]=true;
		}else{
			$array_response["success"]=false;
			$array_response["errno"]=$salvar->errno;
			$array_response["error"]=$salvar->error;
			$array_response["op"]="insert";
		}
	}
    echo json_encode($array_response);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=listarAvaliacoes
Retorno json object
Retorna a lista com todas as avaliações relacionadas a um hospital.

Parâmetros de entrada
id_hospita = id do que recebeu as avaliações listadas.

Ex de entrada válida
{"id_hospital":"13"}

Ex de retorno de sucesso
{
	"success": true,
	"avaliacoes": [{
		"id": "12",
		"comentario": "Minha opini\u00e3o",
		"nota": "1",
		"dt_registro": "2016-01-09 15:51:20",
		"id_hospital": "13",
		"id_usuario": "15",
		"usuario": {
			"id": "15",
			"cpf": "00000000000",
			"dt_Nascimento": "0000-00-00 00:00:00",
			"dt_Registro": null,
			"email": "1052369734827642@userfacebook.com",
			"foto": "https:\/\/graph.facebook.com\/1052369734827642\/picture?height=300&width=300&migration_overrides=%7Boctober_2012%3Atrue%7D",
			"nome": "Raphael Rocha",
			"senha": "tweghcewuy98378@hisgad87",
			"sexo": "0"
		}
	}]
}

*/
function listarAvaliacoes($json){
	$array_response = array();
	$idHospital = $json->id_hospital;

	$sql = "select * from avalia_hospital where id_hospital = $idHospital";
	$avaliacoes = DB::queryAll($sql);

	if(sizeof($avaliacoes)>0){
		$tempAv = array();
		foreach ($avaliacoes as $avaliacao) {
			$idUsuario = $avaliacao["id_usuario"];
			$sql = "select * from usuario where id=$idUsuario";
			$usuario = DB::queryOne($sql);
			$avaliacao["usuario"] = $usuario;

			array_push($tempAv, $avaliacao);
		}
		$array_response["success"]=true;
		$array_response["avaliacoes"] = $tempAv;
	}else{
		$array_response["success"]=true;
	}
	echo json_encode($array_response);
}

/*
URL: http://IP_OU_NOME_SERVIDOR/wscsm/server.php?f=criarUsuario
Retorno json objetc
Cria um usuário caso ele não exita.

Parâmentros de entrada
nome = nome da pessoa
email = email informado, será o login
senha = senha informada
foto = imagem em base64 para salvar no bando de dados para usuários criados vai app, ou url para a foto de perfil do facebook. (O salvamento da imagem, ainda não está implementado)
cpf = cpf do usuário criado, esse valor vem zerado da app, pois não estamos pedindo o seu preechimento ainda.
dtNasc = data de nascimento do usuário
sexo = sexo do usuário, 0 para masculino 1 para feminino

Ex de entrada válida
{
	"sexo": "0",
	"cpf": "00000000000",
	"nome": "Raphael Rocha",
	"foto": "https://graph.facebook.com/1052369734827642/picture?height\u003d300\u0026width\u003d300\u0026migration_overrides\u003d%7Boctober_2012%3Atrue%7D",
	"senha": "tweghcewuy98378@hisgad87",
	"email": "1052369734827642@userfacebook.com"
}

Ex de retorno de sucesso.
{
	"success": true,
	"usuario": {
		"id": "15",
		"cpf": "00000000000",
		"dt_Nascimento": "0000-00-00 00:00:00",
		"dt_Registro": null,
		"email": "1052369734827642@userfacebook.com",
		"foto": "https:\/\/graph.facebook.com\/1052369734827642\/picture?height=300&width=300&migration_overrides=%7Boctober_2012%3Atrue%7D",
		"nome": "Raphael Rocha",
		"senha": "tweghcewuy98378@hisgad87",
		"sexo": "0"
	}
}

Ex de retorno de erro.
{"success":false}
*/
function criarUsuario($json){
	$array_response = array();
	$nome = $json->nome;
	$email = $json->email;
	$senha = $json->senha;
	$foto = $json->foto;

	$cpf =  $json->cpf;
	$dtNacto = $json->dtNasc;
	$sexo = $json->sexo;;

	$sql = "insert into usuario (nome, email, senha, foto, cpf, dt_Nascimento, sexo) values ('$nome', '$email', '$senha', '$foto', '$cpf', '$dtNacto', '$sexo')";
	$salvar = DB::execute($sql);

	$sql = "select * from usuario where email = '$email' and senha = '$senha'";
	$usuario = DB::queryOne($sql);
	if($usuario){
		$array_response["success"]=true;
		$array_response["usuario"]=$usuario;
	}else{
		$array_response["success"]=false;
	}
	echo json_encode($array_response);
}
	
?>