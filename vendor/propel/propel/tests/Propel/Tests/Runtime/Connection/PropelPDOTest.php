<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Tests\Runtime\Connection;

use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;
use Propel\Tests\Bookstore\Author;
use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\Map\AuthorTableMap;
use Propel\Tests\Bookstore\Map\BookTableMap;

use Propel\Runtime\Propel;
use Propel\Runtime\Connection\Exception\RollbackException;
use Propel\Runtime\Connection\PropelPDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Monolog\Logger;
use Monolog\Handler\AbstractHandler;

use \PDO;
use \PDOException;
use \Exception;

/**
 * Test for PropelPDO subclass.
 */
class PropelPDOTest extends BookstoreTestBase
{
    protected function setUp()
    {
        $this->con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
    }

    protected function tearDown()
    {
    }

    public function testSetAttribute()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $this->assertFalse($con->getAttribute(PropelPDO::PROPEL_ATTR_CACHE_PREPARES));
        $con->setAttribute(PropelPDO::PROPEL_ATTR_CACHE_PREPARES, true);
        $this->assertTrue($con->getAttribute(PropelPDO::PROPEL_ATTR_CACHE_PREPARES));

        $con->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $this->assertEquals(PDO::CASE_LOWER, $con->getAttribute(PDO::ATTR_CASE));
    }

    public function testCommitBeforeFetch()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        AuthorTableMap::doDeleteAll($con);
        $a = new Author();
        $a->setFirstName('Test');
        $a->setLastName('User');
        $a->save($con);

        $con->beginTransaction();
        $stmt = $con->prepare('SELECT author.FIRST_NAME, author.LAST_NAME FROM author');

        $stmt->execute();
        $con->commit();
        $authorArr = array(0 => 'Test', 1 => 'User');

        $i = 0;
        try {
            $row = $stmt->fetch( PDO::FETCH_NUM );
            $stmt->closeCursor();
            $this->assertEquals($authorArr, $row, 'PDO driver supports calling $stmt->fetch after the transaction has been closed');
        } catch (PDOException $e) {
            $this->fail("PDO driver does not support calling \$stmt->fetch after the transaction has been closed.\nFails with error ".$e->getMessage());
        }
    }

	public function testPdoSignature()
	{
		$con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
		$stmt = $con->prepare('SELECT author.FIRST_NAME, author.LAST_NAME FROM author');
		$stmt->execute();
		$stmt->fetchAll(\PDO::FETCH_COLUMN, 0); // should not throw exception: Third parameter not allowed for PDO::FETCH_COLUMN
	}

    public function testCommitAfterFetch()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        AuthorTableMap::doDeleteAll($con);
        $a = new Author();
        $a->setFirstName('Test');
        $a->setLastName('User');
        $a->save($con);

        $con->beginTransaction();
        $stmt = $con->prepare('SELECT author.FIRST_NAME, author.LAST_NAME FROM author');

        $stmt->execute();
        $authorArr = array(0 => 'Test', 1 => 'User');

        $i = 0;
        $row = $stmt->fetch( PDO::FETCH_NUM );
        $stmt->closeCursor();
        $con->commit();
        $this->assertEquals($authorArr, $row, 'PDO driver supports calling $stmt->fetch before the transaction has been closed');
    }

    public function testNestedTransactionCommit()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $driver = $con->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->assertEquals(0, $con->getNestedTransactionCount(), 'nested transaction is equal to 0 before transaction');
        $this->assertFalse($con->isInTransaction(), 'PropelPDO is not in transaction by default');

        $con->beginTransaction();

        $this->assertEquals(1, $con->getNestedTransactionCount(), 'nested transaction is incremented after main transaction begin');
        $this->assertTrue($con->isInTransaction(), 'PropelPDO is in transaction after main transaction begin');

        try {

            $a = new Author();
            $a->setFirstName('Test');
            $a->setLastName('User');
            $a->save($con);
            $authorId = $a->getId();
            $this->assertNotNull($authorId, "Expected valid new author ID");

            $con->beginTransaction();

            $this->assertEquals(2, $con->getNestedTransactionCount(), 'nested transaction is incremented after nested transaction begin');
            $this->assertTrue($con->isInTransaction(), 'PropelPDO is in transaction after nested transaction begin');

            try {

                $a2 = new Author();
                $a2->setFirstName('Test2');
                $a2->setLastName('User2');
                $a2->save($con);
                $authorId2 = $a2->getId();
                $this->assertNotNull($authorId2, "Expected valid new author ID");

                $con->commit();

                $this->assertEquals(1, $con->getNestedTransactionCount(), 'nested transaction decremented after nested transaction commit');
                $this->assertTrue($con->isInTransaction(), 'PropelPDO is in transaction after main transaction commit');

            } catch (Exception $e) {
                $con->rollBack();
                throw $e;
            }

            $con->commit();

            $this->assertEquals(0, $con->getNestedTransactionCount(), 'nested transaction decremented after main transaction commit');
            $this->assertFalse($con->isInTransaction(), 'PropelPDO is not in transaction after main transaction commit');

        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }

        AuthorTableMap::clearInstancePool();
        $at = AuthorQuery::create()->findPk($authorId);
        $this->assertNotNull($at, "Committed transaction is persisted in database");
        $at2 = AuthorQuery::create()->findPk($authorId2);
        $this->assertNotNull($at2, "Committed transaction is persisted in database");
    }

    /**
     * @link       http://propel.phpdb.org/trac/ticket/699
     */
    public function testNestedTransactionRollBackRethrow()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $driver = $con->getAttribute(PDO::ATTR_DRIVER_NAME);

        $con->beginTransaction();
        try {

            $a = new Author();
            $a->setFirstName('Test');
            $a->setLastName('User');
            $a->save($con);
            $authorId = $a->getId();

            $this->assertNotNull($authorId, "Expected valid new author ID");

            $con->beginTransaction();

            $this->assertEquals(2, $con->getNestedTransactionCount(), 'nested transaction is incremented after nested transaction begin');
            $this->assertTrue($con->isInTransaction(), 'PropelPDO is in transaction after nested transaction begin');

            try {
                $con->exec('INVALID SQL');
                $this->fail("Expected exception on invalid SQL");
            } catch (PDOException $x) {
                $con->rollBack();

                $this->assertEquals(1, $con->getNestedTransactionCount(), 'nested transaction decremented after nested transaction rollback');
                $this->assertTrue($con->isInTransaction(), 'PropelPDO is in transaction after main transaction rollback');

                throw $x;
            }

            $con->commit();
            $this->fail("Commit should never been reached, because of invalid nested SQL!");
        } catch (Exception $x) {
            $con->rollBack();
            // do not re-throw... we are already at the toplevel
        }

        $this->assertEquals(0, $con->getNestedTransactionCount(), 'nested transaction decremented after transaction rollback');
        $this->assertFalse($con->isInTransaction(), 'PropelPDO is no longer in transaction after transaction rollback');

        AuthorTableMap::clearInstancePool();
        $at = AuthorQuery::create()->findPk($authorId);
        $this->assertNull($at, "Rolled back transaction is not persisted in database");
    }

    /**
     * @link http://trac.propelorm.org/ticket/699
     * @group mysql
     */
    public function testNestedTransactionRollBackSwallow()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $driver = $con->getAttribute(PDO::ATTR_DRIVER_NAME);

        $con->beginTransaction();
        try {

            $a = new Author();
            $a->setFirstName('Test');
            $a->setLastName('User');
            $a->save($con);

            $authorId = $a->getId();
            $this->assertNotNull($authorId, "Expected valid new author ID");

            $con->beginTransaction();
            try {

                $a2 = new Author();
                $a2->setFirstName('Test2');
                $a2->setLastName('User2');
                $a2->save($con);
                $authorId2 = $a2->getId();
                $this->assertNotNull($authorId2, "Expected valid new author ID");

                $con->exec('INVALID SQL');
                $this->fail("Expected exception on invalid SQL");
            } catch (PDOException $e) {
                $con->rollBack();
                // NO RETHROW
            }

            $a3 = new Author();
            $a3->setFirstName('Test2');
            $a3->setLastName('User2');
            $a3->save($con);

            $authorId3 = $a3->getId();
            $this->assertNotNull($authorId3, "Expected valid new author ID");

            $con->commit();
            $this->fail("Commit fails after a nested rollback");
        } catch (RollbackException $e) {
            $this->assertTrue(true, "Commit fails after a nested rollback");
            $con->rollback();
        }

        AuthorTableMap::clearInstancePool();
        $at = AuthorQuery::create()->findPk($authorId);
        $this->assertNull($at, "Rolled back transaction is not persisted in database");
        $at2 = AuthorQuery::create()->findPk($authorId2);
        $this->assertNull($at2, "Rolled back transaction is not persisted in database");
        $at3 = AuthorQuery::create()->findPk($authorId3);
        $this->assertNull($at3, "Rolled back nested transaction is not persisted in database");
    }

    public function testNestedTransactionForceRollBack()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $driver = $con->getAttribute(PDO::ATTR_DRIVER_NAME);

        // main transaction
        $con->beginTransaction();

        $a = new Author();
        $a->setFirstName('Test');
        $a->setLastName('User');
        $a->save($con);
        $authorId = $a->getId();

        // nested transaction
        $con->beginTransaction();

        $a2 = new Author();
        $a2->setFirstName('Test2');
        $a2->setLastName('User2');
        $a2->save($con);
        $authorId2 = $a2->getId();

        // force rollback
        $con->forceRollback();

        $this->assertEquals(0, $con->getNestedTransactionCount(), 'nested transaction is null after nested transaction forced rollback');
        $this->assertFalse($con->isInTransaction(), 'PropelPDO is not in transaction after nested transaction force rollback');

        AuthorTableMap::clearInstancePool();
        $at = AuthorQuery::create()->findPk($authorId);
        $this->assertNull($at, "Rolled back transaction is not persisted in database");
        $at2 = AuthorQuery::create()->findPk($authorId2);
        $this->assertNull($at2, "Forced Rolled back nested transaction is not persisted in database");
    }

    public function testLatestQuery()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $con->setLastExecutedQuery(123);
        $this->assertEquals(123, $con->getLastExecutedQuery(), 'PropelPDO has getter and setter for last executed query');
    }

    public function testLatestQueryMoreThanTenArgs()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $c = new Criteria();
        $c->add(BookTableMap::COL_ID, array(1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1), Criteria::IN);
        $books = BookQuery::create(null, $c)->find($con);
        $expected = $this->getSql("SELECT book.ID, book.TITLE, book.ISBN, book.PRICE, book.PUBLISHER_ID, book.AUTHOR_ID FROM `book` WHERE book.ID IN (1,1,1,1,1,1,1,1,1,1,1,1)");
        $this->assertEquals($expected, $con->getLastExecutedQuery(), 'PropelPDO correctly replaces arguments in queries');
    }

    public function testQueryCount()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $count = $con->getQueryCount();
        $con->incrementQueryCount();
        $this->assertEquals($count + 1, $con->getQueryCount(), 'PropelPDO has getter and incrementer for query count');
    }

    public function testUseDebug()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $con->useDebug(false);
        $stmtClass = $con->getAttribute(PDO::ATTR_STATEMENT_CLASS);
        $expectedClass = (defined('HHVM_VERSION') ? '\\' : '') . 'Propel\Runtime\Adapter\Pdo\PdoStatement';

        $this->assertEquals($expectedClass, $stmtClass[0], 'Statement is Propel Statement when debug is false');
        $con->useDebug(true);
        $stmtClass = $con->getAttribute(PDO::ATTR_STATEMENT_CLASS);
        $this->assertEquals($expectedClass, $stmtClass[0], 'Statement is Propel Statement when debug is true');
    }

    public function testDebugLatestQuery()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $c = new Criteria();
        $c->add(BookTableMap::COL_TITLE, 'Harry%s', Criteria::LIKE);

        $con->useDebug(false);
        $this->assertEquals('', $con->getLastExecutedQuery(), 'PropelPDO reinitializes the latest query when debug is set to false');

        $books = BookQuery::create(null, $c)->find($con);
        $this->assertEquals('', $con->getLastExecutedQuery(), 'PropelPDO does not update the last executed query when useLogging is false');

        $con->useDebug(true);
        $books = BookQuery::create(null, $c)->find($con);
        $latestExecutedQuery = $this->getSql("SELECT book.ID, book.TITLE, book.ISBN, book.PRICE, book.PUBLISHER_ID, book.AUTHOR_ID FROM `book` WHERE book.TITLE LIKE 'Harry%s'");
        $this->assertEquals($latestExecutedQuery, $con->getLastExecutedQuery(), 'PropelPDO updates the last executed query when useLogging is true');

        BookTableMap::doDeleteAll($con);
        $latestExecutedQuery = $this->getSql("DELETE FROM `book`");
        $this->assertEquals($latestExecutedQuery, $con->getLastExecutedQuery(), 'PropelPDO updates the last executed query on delete operations');

        $sql = 'DELETE FROM book WHERE 1=1';
        $con->exec($sql);
        $this->assertEquals($sql, $con->getLastExecutedQuery(), 'PropelPDO updates the last executed query on exec operations');

        $sql = 'DELETE FROM book WHERE 2=2';
        $con->query($sql);
        $this->assertEquals($sql, $con->getLastExecutedQuery(), 'PropelPDO updates the last executed query on query operations');

        $stmt = $con->prepare('DELETE FROM book WHERE 1=:p1');
        $stmt->bindValue(':p1', '2');
        $stmt->execute();
        $this->assertEquals("DELETE FROM book WHERE 1='2'", $con->getLastExecutedQuery(), 'PropelPDO updates the last executed query on prepared statements');

        $con->useDebug(false);
        $this->assertEquals('', $con->getLastExecutedQuery(), 'PropelPDO reinitializes the latest query when debug is set to false');

        $con->useDebug(true);
    }

    public function testDebugQueryCount()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);
        $c = new Criteria();
        $c->add(BookTableMap::COL_TITLE, 'Harry%s', Criteria::LIKE);

        $con->useDebug(false);
        $this->assertEquals(0, $con->getQueryCount(), 'PropelPDO does not update the query count when useLogging is false');

        $books = BookQuery::create(null, $c)->find($con);
        $this->assertEquals(0, $con->getQueryCount(), 'PropelPDO does not update the query count when useLogging is false');

        $con->useDebug(true);
        $books = BookQuery::create(null, $c)->find($con);
        $this->assertEquals(1, $con->getQueryCount(), 'PropelPDO updates the query count when useLogging is true');

        BookTableMap::doDeleteAll($con);
        $this->assertEquals(2, $con->getQueryCount(), 'PropelPDO updates the query count on delete operations');

        $sql = 'DELETE FROM book WHERE 1=1';
        $con->exec($sql);
        $this->assertEquals(3, $con->getQueryCount(), 'PropelPDO updates the query count on exec operations');

        $sql = 'DELETE FROM book WHERE 2=2';
        $con->query($sql);
        $this->assertEquals(4, $con->getQueryCount(), 'PropelPDO updates the query count on query operations');

        $stmt = $con->prepare('DELETE FROM book WHERE 1=:p1');
        $stmt->bindValue(':p1', '2');
        $stmt->execute();
        $this->assertEquals(5, $con->getQueryCount(), 'PropelPDO updates the query count on prepared statements');

        $con->useDebug(false);
        $this->assertEquals(0, $con->getQueryCount(), 'PropelPDO reinitializes the query count when debug is set to false');

        $con->useDebug(true);
    }

    public function testDebugLog()
    {
        $con = Propel::getServiceContainer()->getConnection(BookTableMap::DATABASE_NAME);

        // save data to return to normal state after test
        $logger = $con->getLogger();
        $logMethods = $con->getLogMethods();

        $testLog = new Logger('debug');
        $handler = new LastMessageHandler();
        $testLog->pushHandler($handler);
        $con->setLogger($testLog);
        $con->setLogMethods(array(
            'exec',
            'query',
            'execute',
            'beginTransaction',
            'commit',
            'rollBack',
        ));
        $con->useDebug(true);

        $con->beginTransaction();
        // test transaction log
        $this->assertEquals('Begin transaction', $handler->latestMessage, 'PropelPDO logs begin transaction in debug mode');

        $con->commit();
        $this->assertEquals('Commit transaction', $handler->latestMessage, 'PropelPDO logs commit transaction in debug mode');

        $con->beginTransaction();
        $con->rollBack();
        $this->assertEquals('Rollback transaction', $handler->latestMessage, 'PropelPDO logs rollback transaction in debug mode');

        $con->beginTransaction();
        $handler->latestMessage = '';
        $con->beginTransaction();
        $this->assertEquals('', $handler->latestMessage, 'PropelPDO does not log nested begin transaction in debug mode');
        $con->commit();
        $this->assertEquals('', $handler->latestMessage, 'PropelPDO does not log nested commit transaction in debug mode');
        $con->beginTransaction();
        $con->rollBack();
        $this->assertEquals('', $handler->latestMessage, 'PropelPDO does not log nested rollback transaction in debug mode');
        $con->rollback();

        // test query log
        $con->beginTransaction();

        $c = new Criteria();
        $c->add(BookTableMap::COL_TITLE, 'Harry%s', Criteria::LIKE);

        $books = BookQuery::create(null, $c)->find($con);
        $latestExecutedQuery = $this->getSql("SELECT book.ID, book.TITLE, book.ISBN, book.PRICE, book.PUBLISHER_ID, book.AUTHOR_ID FROM `book` WHERE book.TITLE LIKE 'Harry%s'");
        $this->assertEquals($latestExecutedQuery, $handler->latestMessage, 'PropelPDO logs queries and populates bound parameters in debug mode');

        BookTableMap::doDeleteAll($con);
        $latestExecutedQuery = $this->getSql("DELETE FROM `book`");
        $this->assertEquals($latestExecutedQuery, $handler->latestMessage, 'PropelPDO logs deletion queries in debug mode');

        $latestExecutedQuery = 'DELETE FROM book WHERE 1=1';
        $con->exec($latestExecutedQuery);
        $this->assertEquals($latestExecutedQuery, $handler->latestMessage, 'PropelPDO logs exec queries in debug mode');

        $con->commit();

        // return to normal state after test
        $con->setLogger($logger);
        $con->setLogMethods($logMethods);
    }
}

class LastMessageHandler extends AbstractHandler
{
    public $latestMessage = '';

    public function handle(array $record)
    {
        $this->latestMessage = (string) $record['message'];

        return false === $this->bubble;
    }
}
