<?php

require_once '../vendor/autoload.php';
require_once 'PHPUnit/Autoload.php';
require_once 'Config.php';

use PostgresDBAL\Database;
use PostgresDBAL\DatabaseException;

class DatabaseTest extends \PHPUnit_Framework_TestCase
{
	private static $test_tablename;

	private static $connection;

	/*
	 * Handler to trap errors (so we can inspect the last ones generated)
	 */
	private static $last_error = array();
	public static function trap_error_handler($errno, $errstr, $errfile, $errline)
	{
		self::$last_error = array(
			'errno' => $errno,
			'errstr' => $errstr,
			'errfile' => $errfile,
			'errline' => $errline
		);
	}

	public static function setUpBeforeClass()
	{
		set_error_handler('DatabaseTest::trap_error_handler');
		self::$connection = self::GetDatabaseConnection();
		self::$test_tablename = 'unittest_' . time();
	}

	public static function tearDownAfterClass()
	{
		restore_error_handler();
	}

	private static $singletonConnection = null;

    public static function GetDatabaseConnection()
    {
		// Lazy create the singleton connection
		if (is_null(self::$singletonConnection))
		{
			self::$singletonConnection = Database::connect(DATABASE_NAME, DATABASE_USER, DATABASE_PASSWORD, DATABASE_HOST);
		}

		// Return the singleton connection
		return self::$singletonConnection;
    }

	protected function setup()
	{
		$last_error = array();

		$testTableName = self::$test_tablename;
		self::$connection->query('CREATE SEQUENCE ' . $testTableName . '_id');

		$testTableSql = <<< SQL
CREATE TABLE {$testTableName}
(	{$testTableName}_id				integer Primary Key DEFAULT nextval('{$testTableName}_id')
,	{$testTableName}_timestamp		timestamp with time zone NOT NULL DEFAULT now()
,	foo								text
,	bar								text		
,	boo								boolean
)
SQL;
		self::$connection->query($testTableSql);
	}

	protected function tearDown()
	{
		self::$connection->query('DROP TABLE ' . self::$test_tablename);
		self::$connection->query('DROP SEQUENCE ' . self::$test_tablename . '_id');
	}

	public function testNonTransactions()
	{
		$record = array (
			'foo' => 1,
			'bar' => "FUBAR",
			'boo' => true
		);
		self::$connection->insert(self::$test_tablename, $record);

		$result = self::$connection->select(self::$test_tablename, array('foo' => 1));
		$this->assertEquals (1, $result->num_rows());

		// Test that the connection throws an exception on commit
		try
		{
			self::$connection->commit();
			$this->assertTrue(false, "Should have thrown by here");
		}
		catch(DatabaseException $e)
		{
			$this->assertTrue(true);
		}

		// Test that the connection throws an exception on rollback
		try
		{
			self::$connection->rollback();
			$this->assertTrue(false, "Should have thrown by here");
		}
		catch(DatabaseException $e)
		{
			$this->assertTrue(true);
		}
	}

	public function testTransactionRollback()
	{
		$transaction = self::$connection->get_transaction();

		$record = array (
			'foo' => 1,
			'bar' => "FUBAR",
			'boo' => true
		);
		$transaction->insert(self::$test_tablename, $record);

		$result = $transaction->select(self::$test_tablename, array('foo' => 1));
		$this->assertEquals (1, $result->num_rows());

		$transaction->rollback();

		$result = self::$connection->select(self::$test_tablename, array('foo' => 1));
		$this->assertEquals (0, $result->num_rows());

		// Test that the transaction now throws an exception on future calls
		$caught = false;
		try
		{
			$transaction->select(self::$test_tablename, array());
			$this->assertTrue(false, "Should have thrown by here");
		}
		catch(DatabaseException $e)
		{
			$caught = true;
		}
		$this->assertTrue($caught);
	}

	public function testTransactionCommit()
	{
		$transaction = self::$connection->get_transaction();

		$record = array (
			'foo' => 1,
			'bar' => "FUBAR",
			'boo' => true
		);
		$transaction->insert(self::$test_tablename, $record);

		$result = $transaction->select(self::$test_tablename, array('foo' => 1));
		$this->assertEquals (1, $result->num_rows());

		$transaction->commit();

		$result = self::$connection->select(self::$test_tablename, array('foo' => 1));
		$this->assertEquals (1, $result->num_rows());

		// Test that the transaction now throws an exception on future calls
		$caught = false;
		try
		{
			$transaction->select(self::$test_tablename, array());
			$this->assertTrue(false, "Should have thrown by here");
		}
		catch(DatabaseException $e)
		{
			$caught = true;
		}
		$this->assertTrue($caught);
	}

	public function testNestedTransactionRollback()
	{
		// ============================================= First
		$first = self::$connection->get_transaction();

		$record = array (
			'foo' => 1,
			'bar' => 'first',
			'boo' => true
		);
		$first->insert(self::$test_tablename, $record);
		$result = $first->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());

		// --------------------------------------------- Second
		$second = $first->get_transaction();

		$record = array (
			'foo' => 2,
			'bar' => 'second',
			'boo' => false
		);
		$second->insert(self::$test_tablename, $record);
		$result = $second->select(self::$test_tablename, array('bar' => 'second'));
		$this->assertEquals (1, $result->num_rows());
		$result = $second->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Third
		$third = $second->get_transaction();

		$record = array (
			'foo' => 3,
			'bar' => 'third',
			'boo' => true
		);
		$third->insert(self::$test_tablename, $record);
		$result = $third->select(self::$test_tablename, array('bar' => 'third'));
		$this->assertEquals (1, $result->num_rows());
		$result = $third->select(self::$test_tablename, array('bar' => 'second'));
		$this->assertEquals (1, $result->num_rows());
		$result = $third->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Third commit
		$third->commit();

		$result = $second->select(self::$test_tablename, array('bar' => 'third'));
		$this->assertEquals (1, $result->num_rows());
		$result = $second->select(self::$test_tablename, array('bar' => 'second'));
		$this->assertEquals (1, $result->num_rows());
		$result = $second->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());

		// --------------------------------------------- Second rollback
		$second->rollback();

		$result = $first->select(self::$test_tablename, array('bar' => 'third'));
		$this->assertEquals (0, $result->num_rows());
		$result = $first->select(self::$test_tablename, array('bar' => 'second'));
		$this->assertEquals (0, $result->num_rows());
		$result = $first->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());

		// --------------------------------------------- First commit
		$first->commit();

		$result = self::$connection->select(self::$test_tablename, array('bar' => 'third'));
		$this->assertEquals (0, $result->num_rows());
		$result = self::$connection->select(self::$test_tablename, array('bar' => 'second'));
		$this->assertEquals (0, $result->num_rows());
		$result = self::$connection->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());
	}

	/* block to encompass transaction and force falling-out-of-scope */
	private function forgetToCallCommit(DatabaseConnection $connection)
	{
		$transaction = $connection->get_transaction();

		$record = array (
			'foo' => 2,
			'bar' => 'second',
			'boo' => false
		);
		$transaction->insert(self::$test_tablename, $record);
		$result = $transaction->select(self::$test_tablename, array('bar' => 'second'));
		$this->assertEquals (1, $result->num_rows());
		$result = $transaction->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());

		// forget to commit transaction...
	}

	public function testAutoRollback()
	{
		$record = array (
			'foo' => 1,
			'bar' => 'first',
			'boo' => true
		);
		self::$connection->insert(self::$test_tablename, $record);
		$result = self::$connection->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());

		$this->assertEmpty(self::$last_error, "Hm, encountered an error: " . self::$last_error['errstr']);	// no error yet

		$this->forgetToCallCommit(self::$connection);

		$this->assertNotEmpty(self::$last_error);	// now error
		$this->assertEquals("DatabaseTransaction WARNING : Transaction rollback on destruction", self::$last_error['errstr']);

		$result = self::$connection->select(self::$test_tablename, array('bar' => 'second'));
		$this->assertEquals (0, $result->num_rows());
		$result = self::$connection->select(self::$test_tablename, array('bar' => 'first'));
		$this->assertEquals (1, $result->num_rows());
	}

	public function testCallingCommitWhenNotInTransaction()
	{
		$caught = false;
		try
		{
			$this->assertFalse(self::$connection->is_transaction());
			self::$connection->commit();
			$this->assertTrue(false, "Should have thrown by here");
		}
		catch(RuntimeException $e)
		{
			$caught = true;
		}
		$this->assertTrue($caught);
	}

	public function testCallingRollbackWhenNotInTransaction()
	{
		$caught = false;
		try
		{
			$this->assertFalse(self::$connection->is_transaction());
			self::$connection->rollback();
			$this->assertTrue(false, "Should have thrown by here");
		}
		catch(RuntimeException $e)
		{
			$caught = true;
		}
		$this->assertTrue($caught);
	}

	public function testExistsOrInsert()
	{
		// Ensure table empty
		$result = self::$connection->select(self::$test_tablename, array());
		$this->assertEquals(0, $result->num_rows(), 'Ensure table is empty to start');

		// Values for record
		$record = array('foo' => 1);

		// Run to insert first time
		self::$connection->exists_or_insert(self::$test_tablename, $record);
		$result = self::$connection->select(self::$test_tablename, $record);
		$this->assertEquals(1, $result->num_rows(), 'Ensure row is in table');

		// Run again, and should not cause change
		self::$connection->exists_or_insert(self::$test_tablename, $record);
		$result = self::$connection->select(self::$test_tablename, $record);
		$this->assertEquals(1, $result->num_rows(), 'Ensure row continues to be in table');
		$result = self::$connection->select(self::$test_tablename, array());
		$this->assertEquals(1, $result->num_rows(), 'Should only be the one row in the table');
	}
}

?>
