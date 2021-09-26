<?php
class Matheus_WooCommerceImport_IndexController extends Mage_Adminhtml_Controller_Action
{
	public function indexAction()
	{
		$url = $this->getUrl('woocommerce_import/start/index');
		$urlValue = Mage::getSingleton('core/session')->getFormKey();

		$block_content = "
		<form action='$url' method='post' enctype='multipart/form-data'> 
		  <h4>Digite o domínio do seu site WordPress:</h4>
		  <input type='text' name='site-url' id='site-url'>
		  <br><br>
		  <h4><b>Consumer key</b> :</h4>
		  <input type='text' name='consumer-key' id='consumer-key'>
		  <br><br>
		  <h4><b>Consumer secret</b> :</h4>
		  <input type='text' name='consumer-secret' id='consumer-secret'>
		  <br><br>
		  <input type='hidden' name='form_key' value='$urlValue'>
		  <input type='submit' class='btn-export' value='Importar' name='import'>
		</form>
		<style type='text/css'>
		.btn-export{
			display: block;
			border: 0;
			width: 80px;
			background: #4E9CAF;
			padding: 5px 0%;
			text-align: center;
			border-radius: 5px;
			color: white;
			font-weight: bold;
			cursor: pointer;
			line-height: 25px;
		}
		input[type=text], select {
			width: 20%;
			padding: 8px 10px;
			display: inline-block;
			border: 1px solid #ccc;
			border-radius: 4px;
			box-sizing: border-box;
		}
		</style>";
		$this->loadLayout();

		$this->_setActiveMenu('catalog/matheus');
		$block = $this->getLayout()
			->createBlock('core/text', 'woocommerce-block')
			->setText($block_content);

		$this->_addContent($block);
		$this->renderLayout();
	}
}
