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

// Uses RAII for closing result
// Also implements an Iterator to make looping through all the results easier
//
class DatabaseResult implements \Iterator
{
	private $_result;	// pg result object
	private $_row;		// internal iterator row position

	// Constructor
	//
	// assume that the result is passed directly from the pg_query
	// and needs to be checked
    function __construct($result)
	{
		if (FALSE === $result)
		{
			throw new DatabaseResultException(pg_last_error());
		}

		assert(is_resource($result));

        $this->_result = $result;
		$this->_row = 0;
    }

	// Destructor
	//
	function __destruct()
	{
		//echo "Closing Result\n";

        @pg_free_result($this->_result);		// Note: We don't throw Exceptions in destructors
    }

	/**
	 * @return DatabaseResultIterator
	 */
	public function iterator()
	{
		return new DatabaseResultIterator($this);
	}

	public function fetch($row=0)
	{
		assert(is_int($row));

		$record = @pg_fetch_array($this->_result, (int)$row, PGSQL_ASSOC);

		if (FALSE === $record)
		{
			throw new DatabaseResultException('Unable to fetch row: ' . (int)$row);
		}

		return $record;
	}

	//
	// Returns all rows in an array
	//
	public function fetch_all()
	{
		$array = array();
		$num_rows = $this->num_rows();
		
		for ($i=0; $i < $num_rows; ++$i)
		{
			$array[] = $this->fetch($i);
		}

		return $array;
	}

	public function num_rows()
	{
		$num_rows = pg_num_rows($this->_result);

		if (-1 == $num_rows)
		{
			throw new DatabaseResultException('Number of rows failed');
		}

		return $num_rows;
	}

	public function affected_rows()
	{
		$num_rows = pg_affected_rows($this->_result);

		if (-1 == $num_rows)
		{
			throw new DatabaseResultException('Number of rows failed');
		}

		return $num_rows;
	}

	//
	// Will return all the results as an array
	// Handy for converting into JSON
	// The pg_arrays is a array of field names that will be converted from a string {pg array} to a php array
	// This currently only handles integer values in the array
	//
	public function toArray(array $pg_arrays = array())
	{
		$return = array();

		foreach ($this as $row => $record)
		{
			// Scan for any postgresql arrays that need to be converted into PHP arrays
			foreach ($record as $key => $value)
			{
				if (in_array($key, $pg_arrays))
				{
					preg_match_all ('/[\w]+/', $value, $matches);
					assert(isset($matches[0]) and is_array($matches[0]));
					array_walk($matches[0], function(&$value, $index) {
							$value = (int)$value;	// cast values as int
					});
					$record[$key] = $matches[0];
				}
			}
			$return[] = $record;
		}

		//$array['id'] = $this->id();
		//$array['name'] = $this->name();
		//$array['firstname'] = $this->firstname();
		//if (strlen($this->lastname()) > 0) $array['lastname'] = $this->lastname();
		//if (strlen($this->role()) > 0) $array['role'] = $this->role();
		//$member_array =  $this->GroupList();
		//if (count($member_array) > 0) $array['group_member'] = $member_array;
		//$owner_array =  $this->GroupOwnerList();
		//if (count($owner_array) > 0) $array['group_owner'] = $owner_array;
		return $return;
	}

	// ------------------------------------------------------------------------
	// Iterator support

	public function rewind()	{	$this->_row = 0;					}
	public function current()	{	return $this->fetch($this->_row);	}
	public function key()		{	return $this->_row;					}
	public function next()		{	++$this->_row;						}
	public function valid()		{	return ($this->_row >= 0 and $this->_row < $this->num_rows());	}
}

?>
