<?php
/**
 *
 */

App::uses('File', 'Utility');
App::uses('Folder', 'Utility');

/**
 * Upload Behavior
 *
 */
class UploadBehavior extends ModelBehavior {
/**
 * Constructor
 *
 */
	function __construct() {	
		//limpar pasta temporária
		$this->tempFolder = WWW_ROOT . 'files' . DS . 'tmp' . DS;
		
		if(!is_dir($this->tempFolder)) {
			@mkdir($this->tempFolder, '0777');
		}
		else {
			//data limite
			$limite = strtotime('-1 hour');
			
			$p = opendir($this->tempFolder);
			if($p !== false) {
				while(($file = readdir($p)) !== false){
					if(is_file($this->tempFolder . $file)){
						if(filemtime($this->tempFolder . $file) < $limite)
							@unlink($this->tempFolder . $file);
					}
				}
				closedir($p);
			}
		}
	}

/**
 * 
 *
 * @param object $model Reference to model
 * @param array $settings Settings (optional)
 * @return void
 * @access public
 */
	function setup(&$model, $settings = array()) {
		$this->fields[$model->name] =  $settings;
	}

	private function deleteFiles($item, $field, $setting, $model) {		
		$folder = WWW_ROOT . 'files' . DS . strtolower($model->name) . DS;
		
		unlink($folder . $item[$field]['file']);
		foreach($setting['thumbsizes'] as $prefix => $thumbsize) {
			if($prefix == 'normal')
				continue;
			unlink($folder . $prefix . '_' . $item[$field]['file']);
		}
		if($item[$field]['multiple']=='true'){
			$conditions = array($model->name . '.' . $field => $item[$field]['file']);
			$model->deleteAll($conditions, false);
			unset($model->data[$model->name][$field]);
		}
		else {
			$model->data[$model->name][$field] = "";
		}
	}

	function beforeSave(&$model) {
		foreach($this->fields[$model->name] as $field => $setting) {
			if(!isset($model->data[$model->name][$field]) || !is_array($model->data[$model->name][$field])) {				
				continue;
			}
						
			$arquivo = $model->data[$model->name][$field]['file'];
			
			if($model->data[$model->name][$field]['status'] == 'old' && $model->data[$model->name][$field]['multiple'] == 'true') {
				unset($model->data[$model->name]);
				continue;
			}
			
			if($model->data[$model->name][$field]['status'] == 'old') {
				unset($model->data[$model->name][$field]);
				continue;
			}

			elseif ($model->data[$model->name][$field]['status'] == 'rem') {
				$this->deleteFiles($model->data[$model->name], $field, $setting, $model);
				continue;
			}
			
			//separar extensão do arquivo
			$partes = explode('.', $arquivo);
			$ext = array_pop($partes);
			$nomeArquivo = implode($partes, '.');
			
			//copiar para a nova pasta
			$folder = WWW_ROOT . 'files' . DS . strtolower($model->name) . DS;
			$tempFolder = WWW_ROOT . 'files' . DS . 'tmp' . DS;
		
			if(!file_exists($folder))
				mkdir($folder, '0777');
			
			$i = 1;
			$nomeArquivoTemp = $nomeArquivo;
			while(file_exists($folder . $nomeArquivo . '.' . $ext)){
				$nomeArquivo = $nomeArquivoTemp . $i;
				$i++;
			}
			
			//arquivo principal
			copy($tempFolder . $arquivo, $folder . $nomeArquivo . '.' . $ext);
			unlink($tempFolder . $arquivo);
			
			//thumbnails
			foreach($setting['thumbsizes'] as $prefix => $thumbsize) {
				if($prefix == 'normal') continue;
				copy($tempFolder . $prefix . '_' . $arquivo, $folder . $prefix . '_' . $nomeArquivo . '.' . $ext);
				unlink($tempFolder . $prefix . '_' . $arquivo);
			}
			
			//coloca o data com o nome que pode ter sido alterado
			$model->data[$model->name][$field] = $nomeArquivo . '.' . $ext;
		}
		return true;
	}
	
	function beforeDelete(&$model) {
		$data = $model->findById($model->id);
		$folder = WWW_ROOT . 'files' . DS . strtolower($model->name) . DS;
		
		foreach($this->fields as $field => $setting) {
			foreach($setting['thumbsizes'] as $prefix => $thumbsize) {
				if($prefix == 'normal') continue;
				unlink($folder . $prefix . '_' . $data[$model->name][$field]);
			}
			unlink($folder . $data[$model->name][$field]);
		}
		return true;
	}

}
