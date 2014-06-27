<?php
/**
 * @package nxcExtendedAttributeFilter
 * @class   nxcExtendedAttributeFilter
 * @author  Serhey Dolgushev <serhey.dolgushev@nxc.no>
 * @date    12 apr 2010
 **/

class nxcExtendedAttributeFilter {

	public function __construct() {
	}

	public function getFilters( $params ) {
		$return = array(
			'tables'   => null,
			'joins'    => null,
			'columns'  => null,
			'group_by' => null
		);

		if( isset( $params['sub_filters'] ) && is_array( $params['sub_filters'] ) ) {
			$subFilters = $params['sub_filters'];
			foreach( $subFilters as $index => $subFilterInfo ) {
				if(
					isset( $subFilterInfo['callback'] ) === false ||
					is_array( $subFilterInfo['callback'] ) === false ||
					isset( $subFilterInfo['callback']['method_name'] ) === false ||
					is_scalar( $subFilterInfo['callback']['method_name'] ) === false
				) {
					continue;
				}

				$callback   = array();
				$callback[] = isset( $subFilterInfo['callback']['class_name'] ) ? $subFilterInfo['callback']['class_name'] : 'nxcExtendedAttributeFilter';
				$callback[] = $subFilterInfo['callback']['method_name'];

				$params = isset( $subFilterInfo['params'] ) ? $subFilterInfo['params'] : array();
				if( is_array( $params ) === false ) {
					$params = array( $params );
				}
				$params = array_merge( array( 'index' => $index ), $params );

				if( is_callable( $callback ) ) {
					$subFilterResult = call_user_func( $callback, $params );

					foreach( $return as $key => $value ) {
						if( isset( $subFilterResult[ $key ] ) && is_scalar( $subFilterResult[ $key ] ) ) {
							$return[ $key ] = $value . $subFilterResult[ $key ];
						}
					}
				}
			}
		}

		if( is_null( $return['group_by'] ) ) {
			unset( $return['group_by'] );
		}
		return $return;
	}

	public static function userAccount( $params ) {
		$db = eZDB::instance();

		$joins = 'ezuser.contentobject_id = ezcontentobject.id AND ezuser_setting.user_id = ezcontentobject.id AND ';
		if( isset( $params['login'] ) ) {
			$joins .= 'ezuser.login LIKE "%' . $db->escapeString( $params['login'] ) . '%" AND ';
		}
		if( isset( $params['email'] ) ) {
			$joins .= 'ezuser.email LIKE "%' . $db->escapeString( $params['email'] ) . '%" AND ';
		}
		if( isset( $params['enabled'] ) ) {
			$joins .= 'ezuser_setting.is_enabled=' . (int) $params['enabled'] . ' AND ';
		}
		$return = array(
			'tables'  => ', ezuser, ezuser_setting',
			'joins'   => $joins
		);
		return $return;
	}

	public static function relatedObjectList( $params ) {
		$table = 'ol' . $params['index'];
		$joins = ' ezcontentobject_tree.contentobject_id = ' . $table . '.from_contentobject_id AND ezcontentobject_tree.contentobject_version = ' . $table . '.from_contentobject_version AND ';

		if( isset( $params['attribute'] ) ) {
			$attributes   = (array) $params['attribute'];
			$attributeIDs = array();
			foreach( $attributes as $attribute ) {
				$attributeID = $attribute;
				if( is_numeric( $attributeID ) === false ) {
					$attributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $attributeID );
				}
				$attributeIDs[] = $attributeID;
			}
			$attributeIDs = array_unique( $attributeIDs );

			$joins .= '' . $table . '.contentclassattribute_id IN (' . implode( ', ', $attributeIDs ) . ') ';
		}

		if( isset( $params['object_ids'] ) ) {
			$objectIDs     = $params['object_ids'];
			$excludeString = null;
			if( isset( $params['exclude'] ) && $params['exclude'] === true ) {
				$excludeString = is_array( $objectIDs ) ? 'NOT' : '!';
			}
			if( is_array( $objectIDs ) ) {
				foreach( $objectIDs as $key => $id ) {
					if( is_numeric( $id ) === false ) {
						unset( $objectIDs[ $key ] );
					}
				}

				if( count( $objectIDs ) > 0 ) {
					$joins .= ' AND ' . $table . '.to_contentobject_id ' . $excludeString . ' IN (' . join( ',', $objectIDs ) . ') AND ';
				} else {
					return array();
				}
			} else {
				$joins .= ' AND ' . $table . '.to_contentobject_id ' . $excludeString . '=' . (int) $objectIDs . ' AND ';
			}
		} else {
			return array();
		}

		$return = array(
			'tables'  => ', ezcontentobject_link as ' . $table,
			'joins'   => $joins
		);
		return $return;
	}

	public static function reverseRelatedObjectList( $params ) {
		$table = 'rol' . $params['index'];
		$joins = ' ezcontentobject_tree.contentobject_id = ' . $table . '.to_contentobject_id AND ';

		if( isset( $params['attribute'] ) ) {
			$attributeID = $params['attribute'];
			if( is_numeric( $attributeID ) === false ) {
				$attributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $params['attribute'] );
			}
			$joins .= '' . $table . '.contentclassattribute_id = ' . $attributeID . ' AND ';
		}

		if( isset( $params['object_id'] ) ) {
			$object = eZContentObject::fetch( $params['object_id'] );
			if( $object instanceof eZContentObject ) {
				$joins .=
					$table . '.from_contentobject_id = ' . (int) $object->attribute( 'id' )
					. ' AND ' . $table . '.from_contentobject_version = ' . (int) $object->attribute( 'current_version' ) . ' AND ';
			}
		} elseif( isset( $params['object_ids'] ) ) {
			$objectIDs = array();
			foreach( (array) $params['object_ids'] as $key => $id ) {
				if( (int) $id > 0 ) {
					$objectIDs[] = (int) $id;
				}
			}

			if( count( $objectIDs ) > 0 ) {
				$joins .= $table . '.from_contentobject_id IN (' . implode( ', ', $objectIDs ) . ') AND ';
			}
		}

		$return = array(
			'tables'  => ', ezcontentobject_link as ' . $table,
			'joins'   => $joins
		);
		return $return;
	}

	public static function birthday( $params ) {
		$table = 'birthdate' . $params['index'];
		$joins = $table . '.contentobject_id = ezcontentobject.id AND ' . $table . '.version = ezcontentobject.current_version AND ' . $table . '.data_type_string = "ezbirthday" AND ';
		if( isset( $params['start_timestamp'] ) ) {
			$joins .= 'DATE( ' . $table . '.data_text ) >= DATE( "' . date( 'Y-m-d', $params['start_timestamp'] ) . '" ) AND ';
		}
		if( isset( $params['end_timestamp'] ) ) {
			$joins .= 'DATE( ' . $table . '.data_text ) <= DATE( "' . date( 'Y-m-d', $params['end_timestamp'] ) . '" ) AND ';
		}

		$return = array(
			'tables'  => ', ezcontentobject_attribute as ' . $table,
			'joins'   => $joins
		);
		return $return;
	}

	public static function nodeIDs( $params ) {
		$return = array(
			'joins' => 'ezcontentobject_tree.node_id IN (' . implode( ', ', $params['nodeIDs'] ). ') AND '
		);
		return $return;
	}

	public static function geoLocation( $params ) {
		$return = array();

		$latAttributeID = ( is_numeric( $params['attributes']['lat'] ) === false ) ? eZContentObjectTreeNode::classAttributeIDByIdentifier( $params['attributes']['lat'] ) : $params['attributes']['lat'];
		$lngAttributeID = ( is_numeric( $params['attributes']['lon'] ) === false ) ? eZContentObjectTreeNode::classAttributeIDByIdentifier( $params['attributes']['lon'] ) : $params['attributes']['lon'];

		if(
			$latAttributeID !== false
			&& $lngAttributeID !== false
		) {
			$params['lat'] = (float) $params['lat'];
			$params['lon'] = (float) $params['lon'];
			$latTable = 'lat' . $params['index'];
			$lngTable = 'lng' . $params['index'];
			$tables = ', ezcontentobject_attribute as ' . $latTable .
				', ezcontentobject_attribute as ' . $lngTable;

			$locationField = 'data_float';
			if( isset( $params['location_db_field'] ) ) {
				$locationField = $params['location_db_field'];
			}

			$distanceField = '( ( ACOS( SIN( ' . $latTable . '.' . $locationField . ' * PI() / 180 ) * SIN( ' . $params['lat'] . ' * PI() / 180 ) + COS( ' . $latTable . '.' . $locationField . ' * PI() / 180 ) * COS( ' . $params['lat'] . ' * PI() / 180 ) * COS( ( ' . $lngTable . '.' . $locationField . ' - ' . $params['lon'] . ' ) * PI() / 180 ) ) * 180 / PI() ) * 60 * 1.1515 )';
			if(
				isset( $params['distance_measure'] ) === false
				|| $params['distance_measure'] == 'km'
			) {
				$distanceField .= ' * 1.609344';
			}

			$joins = $latTable .'.contentobject_id = ezcontentobject.id
				AND ' . $latTable . '.version = ezcontentobject.current_version
				AND ' . $latTable . '.contentclassattribute_id = ' . $latAttributeID . '
				AND ' . $lngTable . '.contentobject_id = ezcontentobject.id
				AND ' . $lngTable . '.version = ezcontentobject.current_version
				AND ' . $lngTable . '.contentclassattribute_id = ' . $lngAttributeID . '
				AND ';
			if( isset( $params['distance'] ) ) {
				$joins .= $distanceField . ' < ' . $params['distance'] . ' AND ';
			}

			$columns = false;
			if( isset( $params['sort_field'] ) ) {
				$columns = ', ' . $distanceField . ' AS ' . $params['sort_field'];
			}

			$return = array(
				'tables'  => $tables,
				'joins'   => $joins,
				'columns' => $columns
			);
		}

		return $return;
	}

	public static function datesRange( $params ) {
		$attributes = $params['attributes'];
		$range      = $params['range'];

		$startDateAttributeID = isset( $attributes['start'] ) ? $attributes['start'] : false;
		if( is_numeric( $startDateAttributeID ) === false ) {
			$startDateAttributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $startDateAttributeID );
		}
		$endDateAttributeID = isset( $attributes['end'] ) ? $attributes['end'] : false;
		if( is_numeric( $endDateAttributeID ) === false ) {
			$endDateAttributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $endDateAttributeID );
		}
		if(
			$startDateAttributeID === false
			|| $endDateAttributeID === false
		) {
			return array();
		}

		$startDateVar = 'sd' . $params['index'];
		$endDateVar   = 'ed' . $params['index'];
		$tables = ', ezcontentobject_attribute as ' . $startDateVar
			. ', ezcontentobject_attribute as ' . $endDateVar;
		$joins = $startDateVar . '.contentobject_id = ezcontentobject.id AND ' .
			$startDateVar . '.version = ezcontentobject.current_version AND ' .
			$startDateVar . '.contentclassattribute_id = ' . $startDateAttributeID . ' AND ';
		$joins .= $endDateVar . '.contentobject_id = ezcontentobject.id AND ' .
			$endDateVar . '.version = ezcontentobject.current_version AND ' .
			$endDateVar . '.contentclassattribute_id = ' . $endDateAttributeID . ' AND ';

		$startDate = isset( $range['start'] ) ? $range['start'] : false;
		if( is_numeric( $startDate ) === false ) {
			$startDate = (int) strtotime( $startDate );
		}
		$endDate = isset( $range['end'] ) ? $range['end'] : false;
		if( is_numeric( $endDate ) === false ) {
			$endDate = (int) strtotime( $endDate );
		}
		if(
			$startDate > 0
			&& $endDate > 0
		) {
			$startDateValueVar = $startDateVar . '.data_int';
			$endDateValueVar   = $endDateVar . '.data_int';
			$joins .=
				'('
				. '(' . $startDateValueVar . ' >= ' . $startDate . ' AND ' . $startDateValueVar . ' <= ' . $endDate . ') '
				. 'OR (' . $endDateValueVar . ' >= ' . $startDate . ' AND ' . $endDateValueVar . ' <= ' . $endDate . ') '
				. 'OR (' . $startDateValueVar . ' <= ' . $startDate . ' AND ' . $endDateValueVar . ' >= ' . $endDate . ')'
				. ') AND ';
			return array(
				'tables'  => $tables,
				'joins'   => $joins
			);
		}
	}

	public static function groupBy( $params ) {
		return array(
			'group_by' => 'GROUP BY ' . $params['field']
		);
	}

	public static function randomOrder( $params ) {
		return array(
			'columns' => ', RAND() as random_order'
		);
	}

	public static function childNodesCount( $params ) {
		if( isset( $params['class_identifiers'] ) === false ) {
			return array();
		}

		$childClassIDs = array();
		$childClasses  = (array) $params['class_identifiers'];
		foreach( $childClasses as $identifier ) {
			$class = eZContentClass::fetchByIdentifier( $identifier );
			if( $class instanceof eZContentClass ) {
				$childClassIDs[] = $class->attribute( 'id' );
			}
		}
		$childClassIDs = array_unique( $childClassIDs );
		if( count( $childClassIDs ) === 0 ) {
			return array();
		}

		if( isset( $params['sort_field'] ) === false ) {
			$params['sort_field'] = 'child_nodes_count_' . $params['index'];
		}

		return array(
			'columns' => ', ( SELECT COUNT(*) FROM ezcontentobject_tree cnc_nt' . $params['index']
				. ' LEFT JOIN ezcontentobject cnc_co' . $params['index']
				. ' ON ( cnc_co' . $params['index'] . '.id = cnc_nt' . $params['index'] . '.contentobject_id'
				. ' AND cnc_co' . $params['index'] . '.current_version = cnc_nt' . $params['index'] . '.contentobject_version )'
				. ' WHERE cnc_nt' . $params['index'] . '.path_string LIKE CONCAT( \'%/\', ezcontentobject_tree.node_id , \'/%\' )'
				. ' AND cnc_co' . $params['index'] . '.contentclass_id IN (' . implode( ', ', $childClassIDs ) . ') )'
				. ' AS ' . $params['sort_field']
		);
	}

	public static function sortByAttributesGroup( $params ) {
		if( isset( $params['attributes'] ) === false ) {
			return array();
		}

		$possibleSortKeys = array( 'int', 'string' );
		$sortKey = isset( $params['sort_key'] ) ? $params['sort_key'] : $possibleSortKeys[0];
		if( in_array( $sortKey, $possibleSortKeys ) === false ) {
			$sortKey = $possibleSortKeys[0];
		}

		$sortField = isset( $params['sort_field'] )
			? $params['sort_field']
			: 'attrs_group_sort_field_' . $params['index'];

		$classAttributeIDs = array();
		foreach( $params['attributes'] as $attributeID ) {
			if( is_numeric( $attributeID ) === false ) {
				$attributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $attributeID );
			}
			if( $attributeID !== false ) {
				$classAttributeIDs[] = $attributeID;
			}
		}
		$classAttributeIDs = array_unique( $classAttributeIDs );

		if( count( $classAttributeIDs ) === 0 ) {
			return array();
		}

		$tables  = ', ezcontentobject_attribute as attrs_group_sort' . $params['index'];
		$columns = ', attrs_group_sort' . $params['index'] . '.sort_key_'
			. $sortKey . ' AS ' . $sortField;
		$joins = 'attrs_group_sort' . $params['index'] . '.contentobject_id = ezcontentobject.id AND '
			. 'attrs_group_sort' . $params['index'] . '.version = ezcontentobject.current_version AND '
			. 'attrs_group_sort' . $params['index'] . '.contentclassattribute_id IN ('
			. implode( ', ', $classAttributeIDs ) . ') AND ';

		return array(
			'tables'  => $tables,
			'joins'   => $joins,
			'columns' => $columns
		);
	}

	public static function floatComparison( $params ) {
		if(
			isset( $params['attribute'] ) === false
			|| isset( $params['value'] ) === false
		) {
			return array();
		}

		$attributeID = $params['attribute'];
		$value       = $params['value'];
		$comparison  = isset( $params['comparison'] ) ? $params['comparison'] : '=';
		if( is_numeric( $attributeID ) === false ) {
			$attributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $attributeID );
		}

		if( $attributeID === false ) {
			return array();
		}
		if( in_array( $comparison, array( '>', '<', '>=', '<=', '=', '!=' ) ) === false ) {
			return array();
		}

		$db           = eZDB::instance();
		$attributeVar = 'fc' . $params['index'];
		$tables       = ', ezcontentobject_attribute as ' . $attributeVar;
		$joins        = $attributeVar . '.contentobject_id = ezcontentobject.id AND ' .
			$attributeVar . '.version = ezcontentobject.current_version AND ' .
			$attributeVar . '.contentclassattribute_id = ' . $attributeID . ' AND ' .
			$attributeVar . '.data_float ' . $comparison . ' ' . $db->escapeString( $value ) . ' AND ';

		return array(
			'tables'  => $tables,
			'joins'   => $joins
		);
	}

	public static function sortingByFloatAttribute( $params ) {
		if( isset( $params['attribute'] ) === false ) {
			return array();
		}

		$attributeID = $params['attribute'];
		$sortField   = isset( $params['sort_field'] ) ? $params['sort_field'] : 'float_sort_field';
		if( is_numeric( $attributeID ) === false ) {
			$attributeID = eZContentClassAttribute::classAttributeIDByIdentifier( $attributeID );
		}
		if( $attributeID === false ) {
			return array();
		}

		$attributeVar = 'fas' . $params['index'];
		$tables       = ', ezcontentobject_attribute as ' . $attributeVar;
		$joins        = $attributeVar . '.contentobject_id = ezcontentobject.id AND ' .
			$attributeVar . '.version = ezcontentobject.current_version AND ' .
			$attributeVar . '.contentclassattribute_id = ' . $attributeID . ' AND ';

		return array(
			'tables'  => $tables,
			'joins'   => $joins,
			'columns' => ', ' . $attributeVar . '.data_float as ' . $sortField
		);
	}

	public static function multipleParentNodeIDs( $params ) {
		$joins = array();
		foreach( (array) $params['parent_node_ids'] as $parentNodeID ) {
			$joins[] = 'ezcontentobject_tree.path_string LIKE "%/' . $parentNodeID . '/%"';
		}

		$return = array(
			'joins' => count( $joins ) > 0
				? '( ' . implode( ' OR ', $joins ) . ') AND '
				: null
		);
		return $return;
	}

	/**
	 * Sorting by name of related object
	 * @param array $params keys: attribute, [sort_field] (default: relation_sort_field), index
	 * @return array
	 */
	public static function sortingByRelatedObjectName($params) {
		if (isset($params['attribute']) === false) {
			return array();
		}

		$attributeID = $params['attribute'];
		$sortField   = isset($params['sort_field']) ? $params['sort_field'] : 'relation_sort_field';
		if (is_numeric($attributeID) === false) {
			$attributeID = eZContentClassAttribute::classAttributeIDByIdentifier($attributeID);
		}
		if ($attributeID === false) {
			return array();
		}

		$attributeVar  = 'roa' . $params['index'];
		$objectNameVar = 'ron' . $params['index'];
		$tables		= ', ezcontentobject_attribute as ' . $attributeVar . ', ezcontentobject_name as ' . $objectNameVar;
		$joins		 = $attributeVar . '.contentobject_id = ezcontentobject.id AND ' .
			$attributeVar . '.version = ezcontentobject.current_version AND ' .
			$attributeVar . '.contentclassattribute_id = ' . $attributeID . ' AND ' .
			$objectNameVar . '.contentobject_id = ' . $attributeVar . '.data_int AND'
		;

		return array(
			'tables'  => $tables,
			'joins'   => $joins,
			'columns' => ', ' . $objectNameVar . '.name as ' . $sortField
		);
	}
}
