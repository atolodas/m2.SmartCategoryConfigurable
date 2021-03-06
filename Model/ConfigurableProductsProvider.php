<?php
/**
 * Copyright © 2011-2017 Karliuka Vitalii(karliuka.vitalii@gmail.com)
 * 
 * See COPYING.txt for license details.
 */
namespace Faonni\SmartCategoryConfigurable\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Config;

/**
 * ConfigurableProducts Provider
 */
class ConfigurableProductsProvider
{
    /** 
     * Resource Connection
     * 	
     * @var \Magento\Framework\App\ResourceConnection 
     */
    protected $_resource;
    
    /**
     * Config Model
     *	
     * @var \Magento\Catalog\Model\Config
     */
    protected $_config;    

    /**
     * Product Ids
     *	
     * @var array
     */
    protected $_productIds = [];
    
    /**
     * Catalog Product Visibility
     *
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    protected $_catalogProductVisibility;   
    
    /**
     * Initialize Provider
     * 	
     * @param ResourceConnection $resource
     * @param Visibility $catalogProductVisibility 
     * @param Config $config
     */
    public function __construct(
		ResourceConnection $resource,
        Visibility $catalogProductVisibility,
        Config $config
	) {
        $this->_resource = $resource;
        $this->_catalogProductVisibility = $catalogProductVisibility;        
        $this->_config = $config;
    }

    /**
     * Retrieve Display Products Pairs Ids
     * 
     * @param array $ids
     * @return array
     */
    public function getDisplayIds(array $ids)
    {
        $key = md5(json_encode($ids));       
        if (!isset($this->_productIds[$key])) {
            $connection = $this->_resource->getConnection();           
			$select = $connection
				->select()
				->from(
					['e' => $this->_resource->getTableName('catalog_product_entity')], 
					['e.entity_id', 'display_id' => new \Zend_Db_Expr('IF(c.value_id, p.entity_id, IF(s.entity_id, 0, NULL))')]
				)
				->joinLeft(
					['l' => $this->_resource->getTableName('catalog_product_super_link')],
					'l.product_id=e.entity_id', 
					[]
				)  				                  
				->joinLeft(
					['p' => $this->_resource->getTableName('catalog_product_entity')],
					'l.parent_id=p.entity_id', 
					[]
				) 
				->joinLeft(
					['c' => $this->_resource->getTableName('catalog_product_entity_int')],
					join(
						' AND ',
						[
							'c.entity_id = p.entity_id',
							'c.store_id = "0"',
							$connection->quoteInto('p.type_id = ?', Configurable::TYPE_CODE),
							$connection->quoteInto('c.attribute_id = ?', $this->getVisibilityAttributeId()),
							$connection->quoteInto('c.value IN(?)', $this->_catalogProductVisibility->getVisibleInSiteIds())
						]
					),					
					[]
				) 
				->joinLeft(
					['s' => $this->_resource->getTableName('catalog_product_entity_int')],
					join(
						' AND ',
						[
							new \Zend_Db_Expr('c.value_id IS NULL'),
							's.entity_id = e.entity_id',
							's.store_id = "0"',
							$connection->quoteInto('s.attribute_id = ?', $this->getVisibilityAttributeId()),
							$connection->quoteInto('s.value IN(?)', $this->_catalogProductVisibility->getVisibleInSiteIds())
						]
					),					
					[]
				) 
				->where('e.entity_id IN (?)', $ids); 	
            $this->_productIds[$key] = $connection->fetchPairs($select);
        }        
        return $this->_productIds[$key];
    }
	
    /**
     * Retrieve Visibility Attribute Id
     * 
     * @return int
     */
    public function getVisibilityAttributeId()
    {
        return $this->_config->getAttribute(Product::ENTITY, 'visibility')->getId();	
    }	
}
