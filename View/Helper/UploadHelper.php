<?php
/**
 * Part Comunicação Online
 * Helper para uploads
 * @author leandro@part.com.br
 *
 */
class UploadHelper extends AppHelper {
	
	public $helpers = array('Html', 'Form');

	private $partes = array();
	private $configs = null;
	private $data = null;
	private $id = "";
	
	/*Para usar com relacionamento hasmany*/
	private $multiple = false;
	private $i = 0; //iterador de multiplos
		
	
	private function init($campo, &$options = array()) {
		
		$defaultOptions = array(
			'label' => null
		);
		$options = array_merge($defaultOptions, $options);
		
				
		/*Define o mapa do campo*/
		$this->partes = explode('.', $campo);
		if(count($this->partes) == 1) {
			array_unshift($this->partes, key($this->request->params['models']));
		}
		
	
		$this->id = join('', $this->partes);
		
		//verificar se o model da imagem é o model principal ou se é um model que faz has many para habilitar o upload multiplo
		if($this->partes[0] != key($this->request->params['models'])) {
			$modelRequest = ClassRegistry::init(key($this->request->params['models']));
			if(isset($modelRequest->hasMany[$this->partes[0]])) {
				$this->multiple = true;
			}
		}
		
		//ler model da imagem
		$model = ClassRegistry::init($this->partes[0]);
		
		if(!isset($model->actsAs['PartUpload.Upload'][end($this->partes)])) {
			throw new Exception('falta configurar no model');
		}
					
		$this->configs = $model->actsAs['PartUpload.Upload'][end($this->partes)];
		
		//lê o data
		if(isset($this->request->data[$this->partes[0]]))
			$this->data = $this->request->data[$this->partes[0]];	
	}
	
	/**
	 * Percorre recursivamente um array com o outro afim de encontrar o valor final.
	 * */
	private function searchData($data, $partes) {
		$posicao = array_shift($partes);
		if(count($partes) > 0) {
			if(!isset($data[$posicao]))
				return null;
			return $this->searchData($data[$posicao], $partes);
		}
		else
			return $data[$posicao];
	}
		
	
	public function beforeRender($viewFile) {
		$this->Html->script('PartUpload.fileuploader', array('block' => 'script'));
	
		//$this->Html->css('PartUpload.fileuploader', null, array('block' => 'css'));
	
		$templateItemImagem = json_encode($this->templateItemImage);
		$templateItemProgress = json_encode($this->templateItemProgress);
	
		$script = <<<EOF
	var partUploaders = new Array();
	
	var templateItemImage = {$templateItemImagem};
	var templateItemProgress = {$templateItemProgress};
	
	var replaceText = function(string, pairs) {
		for(i = 0; i < pairs.length; i++)
			string = string.split('{' + pairs[i][0] + '}').join(pairs[i][1]);
		return string;
	};
		
	$(".upload-delete").live('click', function(){
		var item = $("#" + $(this).attr("data-target"));
		var status = item.find('.upload-status').val();
		if(status == 'new' && confirm('Deseja realmente excluir este envio?')) {
			item.remove();
		}
		if(status == 'old' && confirm('Deseja marcar este arquivo para exclusão?')) {
			item.find('.upload-status').val('rem');
			item.find('.upload-file').css('opacity', '0.5');
			item.find('.upload-filename').css('text-decoration', 'line-through');
			$(this).remove();
		}
	});
EOF;
	
		$this->Html->scriptBlock($script, array('inline' => false));
	}
	
	public function inputImagem($campo, $options = array()) {
		
		$this->init($campo, $options);
		
		$label = $this->Form->label($campo, $options['label'], array('class' => 'control-label'));
		
		$inputName = 'data[' . implode('][', $this->partes) . ']';
	
		$previewOut = '';
		if($this->data != null) {
			$i = 0;
			if(!$this->multiple) {
				$data = array($this->data);
			}
			else {
				$data = $this->data;
			}
			
			foreach($data as $item) {
				$campo = $item[end($this->partes)];
				$inputNameI = str_replace('0', $i, $inputName);
				if(gettype($campo) == 'array' && $campo['status'] == 'new') { //Caso seja voltando da validação mas seja um campo novo
					$previewOut .= str_replace(
						array('{fieldId}', '{itemId}', '{folder}', '{image}', '{imageName}', '{inputName}', '{status}', '{multiple}'), 
						array($this->id, $i, $this->Html->url('/files/tmp/'), $campo['file'], $campo['file'], $inputNameI, 'new', $this->multiple? 'true': 'false'),
						$this->templateItemImage
					);
				}
				else if(gettype($campo) == 'array' && $campo['status'] == 'old') { //Caso seja voltando da validação, mas seja de um campo que veio do edit.
					$previewOut .= str_replace(
							array('{fieldId}', '{itemId}', '{folder}', '{image}', '{imageName}', '{inputName}', '{status}', '{multiple}'),
							array($this->id, $i, $this->Html->url('/files/' . strtolower($this->partes[0]) . '/'), $campo['file'], $campo['file'], $inputNameI, 'old', $this->multiple? 'true': 'false'),
							$this->templateItemImage
					);
				}
				elseif(gettype($campo) == 'string') { //Caso seja vindo do edit
					$previewOut .= str_replace(
							array('{fieldId}', '{itemId}', '{folder}', '{image}', '{imageName}', '{inputName}', '{status}', '{multiple}' ),
							array($this->id, $i, $this->Html->url('/files/' . strtolower($this->partes[0]) . '/'), $campo, $campo, $inputNameI, 'old', $this->multiple? 'true': 'false'),
							$this->templateItemImage
					);
				}
				$i++;
				$this->i = $i;
			}
			
		}

		if(!$this->multiple)
			$botao = "Subir uma imagem";
		else
			$botao = "Subir imagens";
		
		
		$out = <<<EOF
<div class="control-group">
	{$label}
	<div class="controls">
		<div id="{$this->id}-button" class="btn" type="button"><span class="button-label">{$botao}</span></div>
		<ul id="{$this->id}-preview" class="thumbnails" style="margin-top: 10px">{$previewOut}</ul>
	</div>
</div>
EOF;
	
		
		
		$multiple = $this->multiple ? 'true': 'false';
		$params = json_encode(array(
			'configs' => $this->configs
		));
		
		$script = <<<EOF
partUploaders['{$this->id}'] = new qq.FileUploaderBasic({
	button: $('#{$this->id}-button')[0],
	action: '{$this->Html->url(array('controller' => 'uploads', 'action' => 'image', 'plugin' => 'part_upload', 'admin' => false))}',
	multiple: {$multiple},
	params: {$params},
	debug: true,
	onSubmit: function(id, filename){
		if(!{$multiple}) {
			id = 0;
			$('#{$this->id}-preview').html('');
			$('#{$this->id}-button').hide();		
		}
		else {
			id = id + {$this->i};
		}
		$('#{$this->id}-preview').append(
			replaceText(templateItemProgress,[['fieldId', '{$this->id}'], ['itemId', id.toString()],['file', filename]])
		);
	},
	onProgress: function(id, filename, loaded, total){
		if(!{$multiple}) {
			id = 0;
		}
		else {
			id = id + {$this->i};
		}	
			
		var percent = Math.ceil(loaded / total * 100.0);		
		$('#{$this->id}-progress-' + id +' .bar').css('width', percent.toString() + '%');
	},
	onComplete: function(id, fileName, response){
		if(!{$multiple}) {
			id = 0;	
			$('#{$this->id}-button').show().find(".button-label").html("Substituir imagem");
			var inputName = '$inputName';
		}
		else {
			id = id + {$this->i};
			var inputName = '$inputName'.split('[0]').join('[' + id + ']');			
		}
				
		$('#{$this->id}-loading-' + id).remove();
		
		$('#{$this->id}-preview').append(replaceText(templateItemImage, [
			['fieldId', '{$this->id}'], ['itemId', id.toString()], ['folder', '{$this->Html->url('/')}'], 
			['image', response['path']], ['imageName', response['filename']], ['inputName', inputName], ['status', 'new'], ['multiple', '{$multiple}'] 
		]));
	},
	onCancel: function(id, fileName){
		
	}
	
});
EOF;
		
		
		$this->Html->scriptBlock($script, array('inline' => false));
 
		return $out;
	}
	
	public $templateItemImage = "\t<li id=\"{fieldId}-preview-{itemId}\" class=\"thumbnail\">
		<img src=\"{folder}{image}\" style=\"max-width:120px; max-height:120px\" alt=\"arquivo\" class=\"upload-file\" />
		<div class=\"caption\"><h5 class=\"upload-filename\">{imageName}</h5> <a href=\"javascript:void(0)\" title=\"excluir imagem\" class=\"upload-delete\" data-target=\"{fieldId}-preview-{itemId}\"><i class=\"icon icon-trash\"></i></a></div>
		<input type=\"hidden\" name=\"{inputName}[file]\" value=\"{imageName}\" class=\"upload-file\" />
		<input type=\"hidden\" name=\"{inputName}[status]\" value=\"{status}\" class=\"upload-status\">
		<input type=\"hidden\" name=\"{inputName}[multiple]\" value=\"{multiple}\" class=\"upload-multiple\">
	</li>";
	
	public $templateItemProgress = "\t<li id=\"{fieldId}-loading-{itemId}\" class=\"thumbnail\">
		<div class=\"progress progress-success progress-striped\" style=\"width: 140px\" id=\"{fieldId}-progress-{itemId}\">
			<div class=\"bar\" style=\"width: 0%;\"></div>
		</div>
		<div class=\"caption\"><h5>{file}</h5></div>
	</li>"; 
		
}
