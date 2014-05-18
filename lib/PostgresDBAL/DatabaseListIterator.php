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

/*
 * Container for accessing a list via the foreach pattern
 */

abstract class DatabaseListIterator implements \Iterator
{
	protected $_context = null;
	protected $_keys = array();		// array of primary key identifiers

	// ------------------------------------------------------------------------
	// Implementations need to satisfy the following abstract methods

	abstract protected function key_from_record(array $record);		// returns (int)

	abstract protected function object_for_key($key);		// returns (new ObjectClass)

	// ------------------------------------------------------------------------

	//
	// Expects a database result containing person_id fields.
	// It will loop through the results storing all of them in an internal array
	// for later lazy loading through the DatabaseConnection object (static caching)
	//
	public function __construct(Context $context, DatabaseResult $result)
	{
		$this->_context = $context;

		for ($i = 0; $i < $result->num_rows(); $i++)
		{
			$record = $result->fetch($i);
			$this->_keys[] = $this->key_from_record($record);
		}
	}

	public function rewind()
	{
		reset($this->_keys);
	}

	public function current()
	{
		// get Person object from the connection
		$key = current($this->_keys);
		return $this->object_for_key($key);
	}

	public function key()
	{
		return current($this->_keys);
	}

	public function next()
	{
		$key = next($this->_keys);
		return $this->object_for_key($key);
	}

	public function valid()
	{
		$key = key($this->_keys);
		return ($key !== NULL && $key !== FALSE);
	}
}

?>
