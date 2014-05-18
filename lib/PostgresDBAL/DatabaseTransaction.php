<?php
# Copyright (c) 2013, Richard Chipper
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
class DatabaseTransaction implements DatabaseConnection
{
	private $parent_connection = null;
	private $using_savepoints = false;
	private $savepoint_name = "";
	private $transaction_complete = false;

	// Constructor
	//
	function __construct(DatabaseConnection $parent)
	{
		//echo "Opening Connection\n";

		if ($parent->is_transaction())
		{
			$this->using_savepoints = true;
			$this->savepoint_name = 'transaction_' . uniqid();
			$parent->query('SAVEPOINT ' . pg_escape_identifier($this->savepoint_name));
		}
		else
		{
			$this->using_savepoints = false;
			$parent->query("START TRANSACTION");

		}

		$this->parent_connection = $parent;
	}

	// Destructor
	//
	function __destruct()
	{
		if (! $this->transaction_complete)
		{
			trigger_error("DatabaseTransaction WARNING : Transaction rollback on destruction", E_USER_ERROR);
			$this->rollback();
		}
	}

	// -----------------------------------------------------------------
	// DatabaseConnection Interface

	public function query($sql)
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->query($sql);
	}

	public function select($tablename, array $where_record)
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->select($tablename, $where_record);
	}

	public function insert($tablename, array $record)
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->insert($tablename, $record);
	}

	public function insert_return_id($tablename, array $record)
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->insert_return_id($tablename, $record);
	}

	public function update($tablename, array $update_record, array $where_record)
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->update($tablename, $update_record, $where_record);
	}

	public function update_or_insert($tablename, array $set_record, array $where_record)
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->update_or_insert($tablename, $set_record, $where_record);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see DatabaseConnection::exists_or_insert()
	 */
	public function exists_or_insert($tablename, array $record) {
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->exists_or_insert($tablename, $record);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see DatabaseConnection::record_version()
	 */
	public function record_version($tableName, array $valueFields, array $whereFields) {
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->record_version($tableName, $valueFields, $whereFields);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see DatabaseConnection::mark_versions_deleted()
	 */
	public function mark_versions_deleted($tableName, $matchFieldName, array $continuedValues, array $whereFields) {
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return $this->parent_connection->mark_versions_deleted($tableName, $matchFieldName, $continuedValues, $whereFields);
	}
	

	public function get_transaction()
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		return new DatabaseTransaction($this);
	}

	public function commit()
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		if ($this->using_savepoints)
		{
			// Just releasing the savepoint will not discard the commands executed after it was established.
			$this->parent_connection->query('RELEASE SAVEPOINT ' . pg_escape_identifier($this->savepoint_name));
		}
		else
		{
			$this->parent_connection->query('COMMIT TRANSACTION');
		}
		$this->transaction_complete = true;
	}

	public function rollback()
	{
		if ($this->transaction_complete) throw new DatabaseException("Transaction already finished");
		if ($this->using_savepoints)
		{
			$this->parent_connection->query('ROLLBACK TRANSACTION TO SAVEPOINT ' . pg_escape_identifier($this->savepoint_name));
			$this->parent_connection->query('RELEASE SAVEPOINT ' . pg_escape_identifier($this->savepoint_name));
		}
		else
		{
			$this->parent_connection->query('ROLLBACK TRANSACTION');
		}
		$this->transaction_complete = true;
	}

	public function is_transaction()
	{
		return true;
	}
}
