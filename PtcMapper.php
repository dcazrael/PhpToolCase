<?php

	/**
	* PHP TOOLCASE OBJECT RELATIONAL MAPPING CLASS
	* PHP version 5.3
	* @category 	Libraries
	* @package  	PhpToolCase
	* @version	0.9.1b
	* @author   	Irony <carlo@salapc.com>
	* @license  	http://www.gnu.org/copyleft/gpl.html GNU General Public License
	* @link     	http://phptoolcase.com
	*/

	class PtcMapper
	{
		/**
		* Retrive the query builder from the connection manager and table column names 
		*/
		public function __construct( ){ static::_initialize( ); }
		/**
		* Resets array of values
		*/
		public function reset( ) { $this->_fields = array( ); }
		/**
		* Remove value from fields property
		* @param	string		$key		the table column name
		*/
		public function remove( $key ) { unset( $this->_fields[ $key ] ); }
		/**
		*
		*/
		public function toArray( ){ return $this->_fields; }
		/**
		*
		*/
		public function toJson( ){ return json_encode( $this->_fields ); }
		/**
		* Sets values based on associative array
		* @param	array		$array	associative array with values
		*/
		public static function create( $array ) 
		{ 
			$class = get_called_class( );
			$record = new $class( );
			foreach ( $array as $k => $v ){ $record->$k = $v; }
			return $record;
		}
		/**
		*
		*/
		public static function lastId( )
		{ 
			static::_initialize( );
			return static::_getQB( )->lastId( ); 
		}
		/**
		* Deletes record in table based on id
		* @param
		* @param
		*/
		public function delete( )
		{
			$storage = static::$_storage[ get_called_class( ) ];
			static::_fireEvent( 'deleting' , array( 
					&$this->_fields[ static::$_uniqueKey ] , &$this->_fields ) );
			$result = static::_getQB( )->table( $storage[ 'table' ] )
							 ->delete( $this->_fields[ static::$_uniqueKey ] )
							 ->run( );	 
			static::_fireEvent( 'deleted' , 
				array( &$this->_fields[ static::$_uniqueKey ] , &$this->_fields , &$result ) );
			//$this->reset( );	// reset fields
			return $result;
		}
		/**
		* Inserts a new record in table
		*/
		public function save( )
		{
			$storage = static::$_storage[ get_called_class( ) ];
			if ( empty( $this->_fields ) )
			{
				trigger_error( 'Nothing to save in table' . 
					static::$storage[ 'table' ] . '!' , E_USER_WARNING );
				return false;
			}
			static::_mapFields( );
			$values = $this->_fields;
			static::_fireEvent( 'saving' , array( &$values ) );
			if ( array_key_exists( static::$_uniqueKey , $this->_fields ) ) // update record
			{
				static::_fireEvent( 'updating' , array( &$values ) );
				unset( $values[ static::$_uniqueKey ] );
				$result = static::_getQB( )->table( $storage[ 'table' ] )
							->update( $values , $this->_fields[ static::$_uniqueKey ] )
							->run( );
				static::_fireEvent( 'updated' , array( &$values , &$result ) );
			}
			else // insert new row
			{
				static::_fireEvent( 'inserting' , array( &$values ) );
				$result = static::_getQB( )->table( $storage[ 'table' ] )
								->insert( $this->_fields )->run( ); 
				static::_fireEvent( 'inserted' , array( &$values , &$result ) );
			}
			static::_fireEvent( 'saved' , array( &$values , $result ) );
			//$this->reset( );	// reset fields
			return $result;
		}
		/**
		* Retrieves a record from the table
		* @param
		*/
		public static function find( $id )
		{
			$class = static::_initialize( );
			static::_getQB( )->setFetchMode( PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE , $class );
			return $result = static::_getQB( )->table( static::$_storage[ $class ][ 'table' ] )
								->where( 'id' , '=' , $id )
								->row( );
		}
		/**
		*
		*/
		public static function all( )
		{
			$class = static::_initialize( );
			static::_getQB( )->setFetchMode( PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE , $class );
			return $result = static::_getQB( )->table( static::$_storage[ $class ][ 'table' ] )
								->run( );
		}
		/**
		* Retrieve column names for table
		* @param	string		$table		the table name
		*/
		public static function getColumns( )
		{
			$class = static::_initialize( );
			if ( array_key_exists( 'columns' , static::$_storage[ $class ] ) )
			{ 
				return static::$_storage[ $class ][ 'columns' ]; 
			}
			$cols = array( );
			$columns = static::_getQB( )->run( 'SHOW COLUMNS FROM ' . 
				static::_getQB( )->sanitize( static::$_storage[ $class ][ 'table' ] ) );
			$result_type = 'object'; // start with object as default result type
			if ( @is_array( $columns[ 0 ] ) ){ $result_type = 'array'; }
			switch( $result_type )
			{
				case 'array' :
					foreach ( $columns as  $name )
					{
						$cols[ $name[ 'Field' ] ] = $name[ 'Field' ]; 
					}
				break;
				case 'object' :
				default :
					foreach ( $columns as  $name )
					{
						$cols[ $name->Field ] = $name->Field; 
					}
			}
			return static::$_storage[ $class ][ 'columns' ] = $cols;
		}
		/**
		*
		*/
		public static function observe( $class = null )
		{
			if ( !class_exists( $events_class = static::$_eventClass ) )
			{
				trigger_error( $events_class . ' NOT FOUND!' , E_USER_ERROR );
				return false;
			}
			$class = ( $class ) ? $class : get_called_class( );
			$methods = get_class_methods( $class );
			foreach ( static::$_events as $event )
			{
				if ( in_array( $event , $methods ) )
				{
					$cls = strtolower( $class );
					$events_class::listen( $cls . '.' . $event , $class . '::' . $event );
					static::$_observers[ get_called_class( ) ][ $cls . '.' . $event ] = $event;
				}
			}
		}
		/**
		*
		*/
		public function __set( $key , $value )
		{
			if ( !static::_checkColumn( $key ) ){ return false; }
			return $this->_fields[ $key ] = $value;
		}
		/**
		*
		*/
		public function __get( $key )
		{
			if ( !static::_checkColumn( $key ) ){ return false; }
			return $this->_fields[ $key ];
		}
		/**
		*
		*/
		public static function __callStatic( $method , $args )
		{
			$class = static::_initialize( );
			if ( strpos( $method , 'get_' ) === 0 )
			{
				$meth = explode( 'get_' , $method );
				if ( !static::_checkColumn( $meth[ 1 ] ) ){ return false; }
				$column = ( !array_key_exists( 1 , $args ) ) ? static::$_uniqueKey : $args[ 0 ];
				$value = ( !array_key_exists( 1 , $args ) ) ? $args[ 0 ] : $args[ 1 ];
				return static::_getQB( )->table( static::$_storage[ $class ][ 'table' ] )
							     ->where( $column , '=' , $value )
							     ->row( $meth[ 1 ] );
			}
			else if ( strpos( $method , 'set_' ) === 0 )
			{
				$meth = explode( 'set_' , $method );
				if ( !static::_checkColumn( $meth[ 1 ] ) ){ return false; }
				static::_fireEvent( array( 'saving' , 'updating' ) , array( &$meth , &$args ) );			     
				$result = static::_getQB( )->table( static::$_storage[ $class ][ 'table' ] )
						->where( static::$_uniqueKey , '=' , $args[ 1 ] )
						->update( array( $meth[ 1 ] => $args[ 0 ] ) )
						->run( );
				static::_fireEvent( array( 'updated' , 'saved' ) , array( &$meth , &$args , &$result ) );
				return $result;
			}
			$qb = static::_getQB( )->table( static::$_storage[ $class ][ 'table' ] );
			$qb->setFetchMode( PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE , $class );
			return call_user_func_array( array( $qb , $method ), $args );
		}
		/**
		*
		*/
		protected static $_table = null;
		/**
		*
		*/
		protected static $_uniqueKey = 'id';		
		/**
		*
		*/
		protected static $_map = array( );
		/**
		*
		*/
		protected static $_eventClass = '\PtcEvent';
		/**
		*
		*/
		protected static $_connectionManager = 'PtcDb';
		/**
		*
		*/
		protected static $_connectionName ='default';
		/**
		*
		*/
		protected static $_events = array
		(	
			'inserting' , 'inserted' , 'updating' , 'updated' , 
			'deleting' , 'deleted' , 'saving' , 'saved'
		);
		/**
		*
		*/
		protected static $_observers = array( );
		/**
		*
		*/
		protected static $_storage = array( );
		/**
		*
		*/
		protected $_fields = array( );
		/**
		* Checks if column name exists in table
		* @param	string	$column		the value to check
		*/
		protected static function _checkColumn( $column )
		{
			$storage = static::$_storage[ get_called_class( ) ];
			if ( !array_key_exists( $column , $storage[ 'columns' ] ) && 
							!in_array( $column , static::$_map ) )
			{
				trigger_error( 'Column ' . $column . ' does not exists in table  ' . 
								$storage[ 'table' ] . '!' , E_USER_ERROR );
				return false;
			}
			return true;
		}
		/**
		*
		*/
		protected static function _fireEvent( $event , $data )
		{
			$event = ( is_array( $event ) ) ? $event : array( $event );
			$event_class = static::$_eventClass;
			if ( array_key_exists( $class = get_called_class( ) , static::$_observers ) )
			{
				foreach ( static::$_observers[ $class ] as $k => $v )
				{
					foreach ( $event as $ev )
					{
						if ( $v === $ev ){ $event_class::fire( $k , $data ); }
					}
				}
			}
		}
		/**
		*
		*/		
		protected static function _getQB( )
		{
			return call_user_func( static::$_connectionManager . '::getQB' , static::$_connectionName );	
		}
		/**
		*
		*/		
		protected static function _initialize( )
		{
			$db = static::_getQB( );
			if ( !array_key_exists( $class = get_called_class( ) , static::$_storage ) )
			{
				static::$_storage[ $class ] = array( );
				if ( static::$_table ){ static::$_storage[ $class ][ 'table' ] = static::$_table; }
				else
				{
					static::$_storage[ $class ][ 'table' ] = strpos( $class , '\\' ) ? 
						strtolower( end( explode( '\\' . $class ) ) ) : strtolower( $class );
				}
				$db->run( 'SHOW TABLES LIKE ?' , array( static::$_storage[ $class ][ 'table' ] ) );
				if ( !$db->countRows( ) )
				{ 
					trigger_error( 'Table ' . static::$_storage[ $class ][ 'table' ] . 
								' does not exists, quitting now!' , E_USER_ERROR );
					return false;
				}
				static::$_storage[ $class ][ 'columns' ] = static::getColumns( );
				if ( method_exists( $class , 'boot' ) ){ static::boot( ); }
			}
			return $class;
		}
		/**
		*
		*/
		protected function _mapFields( )
		{
			if ( !empty( static::$_map ) )
			{
				foreach ( static::$_map as $k => $v )
				{
					if ( array_key_exists( $v , $this->_fields ) )
					{
						$this->_fields[ $k ] =  $this->_fields[ $v ];
						unset( $this->_fields[ $v ] );
					}
				}
			}
		}
	}
