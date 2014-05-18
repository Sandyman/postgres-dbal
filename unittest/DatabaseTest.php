<?php

class DatabaseTest extends PHPUnit_Framework_TestCase
{
	private static $test_tablename;

	private static $test_versiontablename;

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
		self::$connection = Yurnit::GetDatabaseConnection();
		self::$test_tablename = 'unittest_' . time();
		self::$test_versiontablename = 'unittest_version_' . time();
	}

	public static function tearDownAfterClass()
	{
		restore_error_handler();
	}

	private static $singletonConnection = null;

    public static function GetDatabaseConnection()
    {
		// Lazy create the singleton connection
		if (is_null(self::$singletonConnection)) {
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
		$caught = false;
		try
		{
			self::$connection->commit();
			$this->assertTrue(false, "Should have thrown by here");
		}
		catch(DatabaseException $e)
		{
			$caught = true;
		}
		$this->assertTrue($caught);

		// Test that the connection throws an exception on rollback
		$caught = false;
		try
		{
			self::$connection->rollback();
			$this->assertTrue(false, "Should have thrown by here");
		}
		catch(DatabaseException $e)
		{
			$caught = true;
		}
		$this->assertTrue($caught);
	}
}

?>
