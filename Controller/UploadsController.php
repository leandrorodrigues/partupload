<?php
App::uses('AppController', 'Controller');
App::import('Vendor','phpthumb', array('file' => 'phpThumb' . DS . 'phpthumb.class.php'));
/**
 * Part Comunicação Online 
 * 
 */
class UploadsController extends AppController {
	public function image() {
		$this->layout = false;
		$this->autoRender = false;
		
		$configs = $this->request->query['configs'];
			
		//upload da imagem
		$tmpfile = uniqid(); //arquivo temporário
		$dir = APP . WEBROOT_DIR . DS . 'files' . DS . 'tmp' . DS;
		
		//move o arquivo original para a pasta temp
		if(isset($this->request->query['qqfile'])) { //Caso a requisição venha via XHR (chrome, firefox, opera)
			$input = fopen('php://input', 'r');
			$target = fopen($dir . $tmpfile , 'w');
				
			stream_copy_to_stream($input, $target);
			fclose($target);
			fclose($input);
			$filename = $this->request->query['qqfile'];
		}
		else { //Caso venha via post com um iframe oculto (internet explorer)
			move_uploaded_file($_FILES['qqfile']['tmp_name'], $dir . $tmpfile);
			$filename = $_FILES['qqfile']['name'];
		}
		
		//separar o nome da extensão
		$parts = explode('.', $filename);
		$ext = array_pop($parts);
		$onlyFilename = join('.', $parts);
		$tmpFilename = $onlyFilename;
		
		$i = 1;
		while(file_exists($dir . $onlyFilename . '.' . $ext)) {
			$onlyFilename = $tmpFilename . $i;
			$i++;
		}
		

		//montar os redimensionamentos	
		foreach($configs['thumbsizes'] as $i => $thumbsize) {
			$thumb = new phpthumb();
			$thumb->setSourceFilename($dir . $tmpfile);
			
			$thumb->setParameter('zc', 1);
			
			if(isset($thumbsize['params'])) {
				foreach($thumbsize['params'] as $param => $value) {
					$thumb->setParameter($param, $value);
				}
			}
			$thumb->setParameter('h', $thumbsize['height']);
			$thumb->setParameter('w', $thumbsize['width']);
			
				
			$thumb->GenerateThumbnail();
			
			if($i == 'normal')
				$i = '';
			else 
				$i =  $i . '_';
			
			
			$thumb->RenderToFile($dir . $i . $onlyFilename  . '.' . $ext);
		}
		@unlink($dir . $tmpfile);
		echo json_encode(array('path' => 'files/tmp/' . $onlyFilename . '.' . $ext, 'filename' => $onlyFilename . '.' . $ext));
		
	}
}
