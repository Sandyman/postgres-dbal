<?php

# Copyright (c) 2013 Richard Chipper, Sander Huijsen
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
#
# 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

namespace PostgresDBAL;

// Uses RAII for closing connection
//
class DatabaseRawConnection implements DatabaseConnection
{
	private $pg_connection = null;

	//private static $_static_cache = array();

	// Constructor
	//
	function __construct($connection_string)
	{
		//echo "Opening Connection\n";

		$this->pg_connection = pg_connect($connection_string);

		if (!$this->pg_connection)
		{
			throw new DatabaseException('Connection Error');
		}

		if (PGSQL_CONNECTION_OK != pg_connection_status($this->pg_connection))
		{
			throw new DatabaseException(pg_errormessage($this->pg_connection));
		}

		// Timezone

		// Serialisation

		// Log?
	}

	// Destructor
	//
	function __destruct()
	{
		//echo "Closing Connection\n";

		@pg_close($this->pg_connection);		// Note: We don't throw Exceptions in destructors
	}

	//
	// Takes an array of key=>value for a database table and returns the equivalent sql with the sql glue used as a separator
	// i.e. $sql_glue or ' , ' for insert sql and ' AND ' for where sql
	//
	private static function record_to_sql(array $record, $sql_glue, $isSet = false)
	{
		assert(is_string($sql_glue));

		$sql_array = array();

		foreach ($record as $key => $field)
		{
			if (is_null($field))
			{
				$sql_array[] = pg_escape_identifier($key) . ($isSet ? " = NULL" : " IS NULL");
			}
			else
			{
				if (is_bool($field))
				{
					if (true === $field)
					{
						$sql_array[] = pg_escape_identifier($key) . " = true";
					}
					else
					{
						$sql_array[] = pg_escape_identifier($key) . " = false";
					}
				}
				else
				{
					$sql_array[] = pg_escape_identifier($key) . " = " . pg_escape_literal($field);
				}
			}
		}

		return implode($sql_glue, $sql_array);
	}

	// -----------------------------------------------------------------
	// DatabaseConnection Interface

	public function query($sql)
	{
		//echo $sql . "<br/>\n";
		return new DatabaseResult(@pg_query($this->pg_connection, $sql));
	}

	//
	// Database select. Supply tablename and where clause. It assumes to return all fields (*),
	// which you can override by providing an array containing the fields you want returned.
	//
	public function select($tablename, array $where_record, array $field_record = array())
	{
		assert(is_string($tablename));
		assert(strlen($tablename) > 0);

		$fields = '*';
		if (count($field_record) > 0) 
		{
			$sql_fields = array();
			foreach ($field_record as $field)
			{
				$sql_fields[] = pg_escape_identifier($field);
			}
			$fields = implode(', ', $sql_fields);
		}		
		$sql = 'SELECT ' . $fields . ' FROM ' . pg_escape_identifier($tablename);

		if( count($where_record) > 0 )
		{
			$sql .= ' WHERE ' . self::record_to_sql($where_record, ' AND ');
		}
		return $this->query($sql);
	}

	//
	// Inserts a single record into a database table.
	// Returns the database result.
	// see also insert_return_id if you are only interested in the resulting database ID
	//
	public function insert($tablename, array $record)
	{
		assert(is_string($tablename));
		assert(strlen($tablename) > 0);

		$columns = "";
		$values = "";

		// Early exit when creating a row with all default values
		if( count($record) == 0 )
		{
			return $this->query('INSERT INTO ' . pg_escape_identifier($tablename) . ' DEFAULT VALUES');
		}

		foreach ( $record as $key => $field )
		{
			$columns .= ',' . pg_escape_identifier($key);

			if (is_null($field))
			{
				$values .= ',NULL';
			}
			else
			{
				//if (is_string($field))
				//{
				//	$values .= ',' . pg_escape_literal($field);
				//}
				//else
				if (is_bool($field))
				{
					// Check for boolean and convert to SQL true or false
					if ($field)
					{
						$values .= ',true';
					}
					else
					{
						$values .= ',false';
					}
				}
				else
				{
					if (is_array($field))
					{
						throw new DatabaseException('Insert can not handle array types');
					}
					else
					{
						$values .= ',' . pg_escape_literal($field);
					}
				}
			}
		}

		$columns = substr($columns,1);        // chop first ','
		$values = substr($values,1);
		$result = $this->query('INSERT INTO ' . pg_escape_identifier($tablename) . ' (' . $columns . ') VALUES (' . $values . ')');
		if ($result->affected_rows() != 1)
		{
			throw new DatabaseException('Expected a single row inserted');
		}

		return $result;
	}

	//
	// Inserts a single record into a database table returning the primary key ID
	// NOTE: Assumes there is a field called "tablename_id" which is the primary key of that table
	//
	public function insert_return_id($tablename, array $record)
	{
		assert(is_string($tablename));
		assert(strlen($tablename) > 0);
		assert(count($record) > 0);

		// Use the sequence ID to know what the inserted ID will be ahead of time
		$sql = 'SELECT nextval(\'' . $tablename . '_id\')';
		$id_result = $this->query($sql);
		$id_record = $id_result->fetch();
		$id = (int)reset($id_record);			// get the ID (reset rewinds the array internal pointer to the first element and returns the value)

		$record[$tablename . '_id'] = $id;		// Include it in the record

		$this->insert($tablename, $record);

		return $id;
	}

	//
	// Updates a row in the database
	// throws an exception when it doesn't update successfully (ie when the row doesn't exist)
	// note: $where_sql is assumed to be escaped correctly (ie safe from sql injection)
	//
	public function update($tablename, array $update_record, array $where_record)
	{
		assert(is_string($tablename));
		assert(strlen($tablename) > 0);
		assert(is_array($update_record));
		assert(count($update_record) > 0);
		assert(is_array($where_record));
		assert(count($where_record) > 0);

		$result = $this->query("UPDATE " . pg_escape_identifier($tablename) . " SET " . self::record_to_sql($update_record, ' , ', true) . " WHERE " . self::record_to_sql($where_record, ' AND '));        // chop first ','

		if ($result->affected_rows() != 1)
		{
			throw new DatabaseUpdateException();
		}

		return $result;
	}

	//
	// Inserts or update depending on if the row is already there
	// Note: This is not guaranteed to work (ie there is a race condition)
	//
	public function update_or_insert($tablename, array $set_record, array $where_record)
	{
		// There should not be overlapping keys in the two arrays
		assert(count(array_intersect_key($set_record, $where_record)) == 0);
		// TODO: Check also that there is complete coverage of required fields for that table.

		$result = null;

		// try updating first and catch any exception (i.e. row doesn't exist) and then try inserting
		try
		{
			$result = $this->update($tablename, $set_record, $where_record);
		}
		catch(DatabaseUpdateException $e)
		{
			$result = $this->insert($tablename, array_merge($set_record, $where_record));
		}

		return $result;
	}

	/**
	 * (non-PHPdoc)
	 * @see DatabaseConnection::exists_or_insert()
	 */
	public function exists_or_insert($tablename, array $record) {
		
		// Determine if row already exists
		$result = $this->select($tablename, $record);
		if ($result->num_rows() > 0) {
			// Row already exists
			$first_record = $result->fetch();
			assert(isset($first_record[$tablename . '_id']));
			return $first_record[$tablename . '_id'];
		}
		
		// Row not exist, so insert it
		return $this->insert_return_id($tablename, $record);
	}

	/**
	 * (non-PHPdoc)
	 * @see DatabaseConnection::record_version()
	 */
	public function record_version($tableName, array $valueFields, array $whereFields) {
		
		// Determine field names from table name
		$idFieldName = $tableName . '_id';
		$deletedFieldName = $tableName . '_deleted';
		
		// Add the deleted field to where condition (if necessary)
		$whereCondition = $whereFields;
		if (! array_key_exists ( $deletedFieldName, $whereFields )) {
			// Include the deleted field
			$whereCondition = array_merge ( $whereFields, array (
					$tableName . '_deleted' => null
			) );
		}
		
		// Obtain the versioned row.
		// Note if more than one versioned row, skip check and close of all versions to start single new version.
		$result = $this->select ( $tableName, $whereCondition );
		$numberOfMatchingRows = $result->num_rows ();
		if ($numberOfMatchingRows == 1) {
			// Determine if same values (where fields have already matched)
			$row = $result->current ();
			$isAllMatch = true;
			foreach ( $valueFields as $key => $value ) {
				if (! $this->isMatchingValues($value, $row [$key])) {
					$isAllMatch = false;
				}
			}
			if ($isAllMatch) {
				// No change required as same values
				return 0;
			}
		}
		
		// Close off any current versions to start new version
		if ($numberOfMatchingRows > 0) {
			$this->update ( $tableName, array (
					$deletedFieldName => 'now()'
			), $whereCondition );
		}
		
		// Insert the new version
		return $this->insert_return_id( $tableName, array_merge ( $valueFields, $whereFields ) );
	}
	
	// Determines if values are matching
	private function isMatchingValues($value, $dbValue) {
		
		// Determine if boolean for comparison
		if ($dbValue == 'false' || $dbValue == 'true') {
			
			// Compare as boolean
			$boolValue = (is_null($value) || ($value == '') || (! boolval($value))) ? 0 : 1;
			$boolDbValue = $dbValue == 'false' ? 0 : 1;				
			return $boolValue == $boolDbValue;
			
		} else {
			// Compare as strings
			return strval($value) == strval($dbValue);
		}
	}

	/**
	 * (non-PHPdoc)s
	 * @see DatabaseConnection::mark_versions_deleted()
	 */
	public function mark_versions_deleted($tableName, $matchFieldName, array $continuedValues, array $whereFields) {
		
		// Add the deleted field to where condition (if necessary)
		$whereCondition = $whereFields;
		$deletedFieldName = $tableName . '_deleted';
		if (! array_key_exists ( $deletedFieldName, $whereFields )) {
			// Include the deleted field
			$whereCondition = array_merge ( $whereFields, array (
					$deletedFieldName => null
			) );
		}
		
		// Iterate over database values that are active (determining versions to mark deleted)
		$result = $this->select ( $tableName, $whereCondition );
		$markDeleteKeys = array ();
		foreach ( $result as $row ) {
			// Check if attribute still active
			$dbKey = $row [$matchFieldName];
			if (! array_key_exists ( $dbKey, $continuedValues )) {
				// Include for removal
				$markDeleteKeys [] = $dbKey;
			}
		}
		
		// Soft delete no longer existing records
		foreach ( $markDeleteKeys as $deleteKey ) {
			
			// Include the field in the where clause
			$deleteCondition = array_merge($whereCondition, array($matchFieldName => $deleteKey));
			$this->update ( $tableName, array (
					$deletedFieldName => 'now()'
			), $deleteCondition );
		}
	}

	public function get_transaction()
	{
		return new DatabaseTransaction($this);
	}

	public function commit()
	{
		throw new DatabaseException("Not a transaction connection. Ensure you are using the return object from get_transaction()");
	}

	public function rollback()
	{
		throw new DatabaseException("Not a transaction connection. Ensure you are using the return object from get_transaction()");
	}

	public function is_transaction()
	{
		return false;
	}
}

?>
