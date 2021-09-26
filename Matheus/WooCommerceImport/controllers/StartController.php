<?php

require __DIR__ . '/../vendor/autoload.php';
use Automattic\WooCommerce\Client;

class Matheus_WooCommerceImport_StartController extends Mage_Adminhtml_Controller_Action
{
	public function indexAction()
	{
		$this->site_url = $this->getRequest()->getPost('site-url');
		$this->ck_key = $this->getRequest()->getPost('consumer-key');
		$this->cs_key = $this->getRequest()->getPost('consumer-secret');

		try {
			$woocommerce = new Client(
				$this->site_url,
				$this->ck_key, // Consumer key 
				$this->cs_key, // Consumer secret
				[
					'version' => 'wc/v3',
				]
			);
			$page = 1;
			$products = [];
			$all_products = [];
			do {
				try {
					$products = $woocommerce->get('products', array('per_page' => 100, 'page' => $page));
				} catch (HttpClientException $e) {
					die("Can't get products: $e");
				}
				$all_products = array_merge($all_products, $products);
				$page++;
			} while (count($products) > 0);
		} catch (Exception $e) {
			exit("Dados inválidos");
		}
		foreach ($all_products as $product) {
			$product = json_decode(json_encode($product), true);
			$p_info = $this->convertValuesToMagento($product);
			if ($p_info["type"] == "configurable") {
				$parent_desc = [$p_info["description"], $p_info["short_description"]];
				$children_id = $p_info["variations"];
				$children_sku = [];
				foreach ($children_id as $child_id) {
					$child = $woocommerce->get('products/' . $child_id);
					$child = json_decode(json_encode($child), true);
					$child["description"] = $parent_desc[0];
					$child["short_description"] = $parent_desc[1];
					$child_info = $this->convertValuesToMagento($child);

					$this->createProduct($child_info, true);
					array_push($children_sku, $child["slug"]);
				}
				$p_info["children_sku"] = $children_sku;
			}
			$this->createProduct($p_info, false);
		}
?>
		<p>Produtos importados com sucesso!</p>
<?php
	}
	private function createProduct($p_info, $is_child)
	{
		$visibility = $is_child ? 1 : 4;
		$sku = $is_child ? "slug" : "sku";
		$is_config = $p_info["type"] == "configurable" ? true : false;
		Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
		$product = Mage::getModel('catalog/product');
		$attributeSet = $product->getDefaultAttributeSetId();
		/** Set values */
		$product
			->setStoreId(1)
			->setWebsiteIds(array(1))
			->setTaxClassId(0)
			->setAttributeSetId($attributeSet)
			->setTypeId($p_info["type"])
			->setSku($p_info[$sku])
			->setName($p_info["name"])
			->setPrice($p_info["regular_price"])
			->setWeight($p_info["weight"])
			->setStatus($p_info["status"])
			->setVisibility($visibility)
			->setDescription($p_info["description"])
			->setShortDescription($p_info["short_description"])
			->setCategoryIds(array(2))
			->setStockData([
				'is_in_stock' => $p_info["stock_status"],
				'qty' => $p_info["stock_quantity"]
			]);
		if ($is_config) {
			$attributes = $p_info["attributes"];
			$config_attr = [];
			foreach ($attributes as $attr) {
				$attr_code = strtolower($attr["name"]);
				$attr_id = $this->getAttributeId($attr_code);
				if ($attr_id != false) {
					array_push($config_attr, $attr_id);
				} else {
					break;
				}
			}
			$product = $this->associateToConfig($product, $config_attr);
		}

		/** Save product */
		try {
			$product->save();
		} catch (Exception $e) {
			$newProduct = $product;
			$product->delete();
			$newProduct->save();
		}
	}
	private function convertValuesToMagento($product)
	{
		$type = array(
			"variation" => "simple",
			"simple" => "simple",
			"variable" => "configurable",
			"grouped" => "grouped"
		);
		$product["type"] = $type[$product["type"]];
		$status = array(
			"publish" => 1,
			"draft" => 2
		);
		$product["status"] = $status[$product["status"]];
		$stock_status = array(
			"instock" => 1,
			"outofstock" => 0
		);
		$product["stock_status"] = $stock_status[$product["stock_status"]];
		return $product;
	}
	private function getAttributeId($attr_code)
	{
		try {
			$attributeId = Mage::getResourceModel('eav/entity_attribute')
				->getIdByCode('catalog_product', $attr_code);
			return $attributeId;
		} catch (Exception $e) {
			echo "<p>O atributo " . $attr_code . " não está cadastrado na plataforma GetCommerce";
			return false;
		}
	}
	private function associateToConfig($product, $config_attr)
	{
		$product->getTypeInstance()->setUsedProductAttributeIds($config_attr);
		$configurableAttributesData = $product->getTypeInstance()->getConfigurableAttributesAsArray();
		$product->setCanSaveConfigurableAttributes(true);
		$product->setConfigurableAttributesData($configurableAttributesData);
		return $product;
	}
}
