<?php

// Copyright (c) 2013, Richard Chipper
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
//
// 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
//
// 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
//
// 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

// Database interface for performing actions against the database
//

namespace PostgresDBAL;

/**
 * Interface DatabaseConnection
 */
interface DatabaseConnection {
	/**
	 *
	 * @param string $sql        	
	 * @return DatabaseResult
	 */
	public function query($sql);
	
	/**
	 *
	 * @param string $tablename        	
	 * @param array $where_record        	
	 * @return DatabaseResult
	 */
	public function select($tablename, array $where_record);
	
	/**
	 *
	 * @param string $tablename        	
	 * @param array $record        	
	 * @return DatabaseResult
	 */
	public function insert($tablename, array $record);
	
	/**
	 *
	 * @param string $tablename        	
	 * @param array $record        	
	 * @return int
	 */
	public function insert_return_id($tablename, array $record);
	
	/**
	 *
	 * @param string $tablename        	
	 * @param array $update_record        	
	 * @param array $where_record        	
	 * @return DatabaseResult
	 */
	public function update($tablename, array $update_record, array $where_record);
	
	/**
	 *
	 * @param string $tablename        	
	 * @param array $set_record        	
	 * @param array $where_record        	
	 * @return DatabaseResult
	 */
	public function update_or_insert($tablename, array $set_record, array $where_record);
	
	/**
	 * Checks whether the record is in the table and if not inserts it.
	 *
	 * @param string $tablename
	 *        	Name of the table.
	 * @param array $record
	 *        	Fields of the record.
	 * @return int The row ID for the existing or newly inserted row
	 */
	public function exists_or_insert($tablename, array $record);
	
	/**
	 * <p>
	 * Records a version within the database.
	 * <p>
	 * This method expects particular fields within the database table:
	 * <ul>
	 * <li>{tablename}_id : providing primary key for table</li>
	 * <li>{tablename}_deleted : providing timestamp of when row stops becoming current version</li>
	 * </ul>
	 *
	 * @param string $tableName
	 *        	Name of the table.
	 * @param array $valueFields
	 *        	Fields to be specified for the version.
	 * @param array $whereFields
	 *        	Condition to match against the version record.
	 * @return int The number of rows inserted
	 */
	public function record_version($tableName, array $valueFields, array $whereFields);
	
	/**
	 * <p>
	 * Closes off all versions that no longer exist (typically because the item being versioned is deleted).
	 * <p>
	 * This works in conjunction with <code>record_version</code>.
	 * <p>
	 * Note that this method only works in matching a single field of the record. This should be the unique identifier of the record being versioned.
	 *
	 * @see record_version
	 *
	 * @param unknown $tableName
	 *        	Name of the table.
	 * @param unknown $matchField
	 *        	Name of field to match if is an existing version.
	 * @param array $continuedValues
	 *        	Array where the keys provides the field values of all existing versions for the scope of the where. The values of the array may be anything (typically just true).
	 * @param array $whereFields
	 *        	Field values to find all versions within scope.
	 */
	public function mark_versions_deleted($tableName, $matchFieldName, array $continuedValues, array $whereFields);
	
	/**
	 *
	 * @return DatabaseTransaction
	 */
	public function get_transaction();
	
	/**
	 */
	public function commit();
	
	/**
	 */
	public function rollback();
	
	/**
	 *
	 * @return bool
	 */
	public function is_transaction();
}

?>
