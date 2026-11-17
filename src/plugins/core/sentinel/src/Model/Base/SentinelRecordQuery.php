<?php

namespace ChurchCRM\Plugins\Sentinel\Model\Base;

use ChurchCRM\Plugins\Sentinel\Model\Map\SentinelRecordTableMap;
use ChurchCRM\Plugins\Sentinel\Model\SentinelRecord as ChildSentinelRecord;
use ChurchCRM\Plugins\Sentinel\Model\SentinelRecordQuery as ChildSentinelRecordQuery;
use Exception;
use PDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\TypedModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use function is_array;
use function is_bool;
use function sprintf;

/**
 * Base class that represents a query for the `sentinel_record` table.
 *
 * @method static orderByRecordKey($order = \Propel\Runtime\ActiveQuery\Criteria::ASC) Order by the record_key column
 * @method static orderByKind($order = \Propel\Runtime\ActiveQuery\Criteria::ASC) Order by the kind column
 * @method static orderByPayload($order = \Propel\Runtime\ActiveQuery\Criteria::ASC) Order by the payload column
 * @method static orderByRevision($order = \Propel\Runtime\ActiveQuery\Criteria::ASC) Order by the revision column
 *
 * @method static groupByRecordKey() Group by the record_key column
 * @method static groupByKind() Group by the kind column
 * @method static groupByPayload() Group by the payload column
 * @method static groupByRevision() Group by the revision column
 *
 * @method static leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method static rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method static innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method static leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method static rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method static innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord|null findOne(\Propel\Runtime\Connection\ConnectionInterface|null $con = null) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord matching the query
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord findOneOrCreate(\Propel\Runtime\Connection\ConnectionInterface|null $con = null) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord matching the query, or a new \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord object populated from the query conditions when no match is found
 *
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord|null findOneByRecordKey(string $record_key) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord filtered by the record_key column
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord|null findOneByKind(string $kind) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord filtered by the kind column
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord|null findOneByPayload(string $payload) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord filtered by the payload column
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord|null findOneByRevision(int $revision) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord filtered by the revision column
 *
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord requirePk($key, \Propel\Runtime\Connection\ConnectionInterface|null $con = null) Return the \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord requireOne(\Propel\Runtime\Connection\ConnectionInterface|null $con = null) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord requireOneByRecordKey(string $record_key) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord filtered by the record_key column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord requireOneByKind(string $kind) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord filtered by the kind column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord requireOneByPayload(string $payload) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord filtered by the payload column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord requireOneByRevision(int $revision) Return the first \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord filtered by the revision column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method \ChurchCRM\Plugins\Sentinel\Model\Base\Collection\SentinelRecordCollection|array<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord>|\Propel\Runtime\Collection\Collection<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord> find(\Propel\Runtime\Connection\ConnectionInterface|null $con = null) Return \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord objects based on current ModelCriteria
 * @method \ChurchCRM\Plugins\Sentinel\Model\Base\Collection\SentinelRecordCollection findObjects(\Propel\Runtime\Connection\ConnectionInterface|null $con = null) Get \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord objects in ObjectCollection
 *
 * @method array<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord>|\Propel\Runtime\Collection\Collection<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord> findByRecordKey(string|array<string> $record_key) Return \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord objects filtered by the record_key column
 * @method array<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord>|\Propel\Runtime\Collection\Collection<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord> findByKind(string|array<string> $kind) Return \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord objects filtered by the kind column
 * @method array<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord>|\Propel\Runtime\Collection\Collection<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord> findByPayload(string|array<string> $payload) Return \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord objects filtered by the payload column
 * @method array<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord>|\Propel\Runtime\Collection\Collection<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord> findByRevision(int|array<int> $revision) Return \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord objects filtered by the revision column
 *
 * @method array<\ChurchCRM\Plugins\Sentinel\Model\SentinelRecord>|\Propel\Runtime\Util\PropelModelPager<mixed> paginate($page = 1, $maxPerPage = 10, \Propel\Runtime\Connection\ConnectionInterface|null $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 * @template ParentQuery of \Propel\Runtime\ActiveQuery\ModelCriteria|null = null
 * @extends \Propel\Runtime\ActiveQuery\TypedModelCriteria<ParentQuery>
 */
abstract class SentinelRecordQuery extends TypedModelCriteria
{
    /**
     * @var string
     */
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of SentinelRecordQuery object.
     *
     * @param string $dbName The database name
     * @param string $modelName The phpName of a model, e.g. 'Book'
     * @param string|null $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct(
        string $dbName = 'default',
        string $modelName = '\\ChurchCRM\\Plugins\\Sentinel\\Model\\SentinelRecord',
        ?string $modelAlias = null
    ) {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildSentinelRecordQuery object.
     *
     * @param string|null $modelAlias The alias of a model in the query
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional Criteria to build the query from
     *
     * @return \ChurchCRM\Plugins\Sentinel\Model\SentinelRecordQuery<null>
     */
    public static function create(?string $modelAlias = null, ?Criteria $criteria = null): Criteria
    {
        if ($criteria instanceof ChildSentinelRecordQuery) {
            return $criteria;
        }
        $query = new ChildSentinelRecordQuery();
        if ($modelAlias !== null) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj = $c->findPk(12, $con);
     * </code>
     *
     * @param string $key Primary key to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con an optional connection object
     *
     * @return \ChurchCRM\Plugins\Sentinel\Model\Base\SentinelRecord|mixed|array the result, formatted by the current formatter
     */
    public function findPk($key, ?ConnectionInterface $con = null)
    {
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(SentinelRecordTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (!$this->isEmpty()) {
            return $this->findPkComplex($key, $con);
        }

        $poolKey = (string)$key;
        $obj = SentinelRecordTableMap::getInstanceFromPool($poolKey);
        if ($obj !== null) {
            return $obj;
        }

        return $this->findPkSimple($key, $con);
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param mixed $key Primary key to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord|null A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con): ?ChildSentinelRecord
    {
        $sql = 'SELECT record_key, kind, payload, revision FROM sentinel_record WHERE record_key = :p0';
        $stmt = $con->prepare($sql);
        if (is_bool($stmt)) {
            throw new PropelException('Failed to initialize statement');
        }
        $stmt->bindValue(':p0', $key, PDO::PARAM_STR);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);

            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row) {
            $obj = new ChildSentinelRecord();
            $obj->hydrate($row);
            $poolKey = (string)$key;
            SentinelRecordTableMap::addInstanceToPool($obj, $poolKey);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param mixed $key Primary key to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
     *
     * @return \ChurchCRM\Plugins\Sentinel\Model\Base\SentinelRecord|mixed|array|null the result, formatted by the current formatter
     */
    protected function findPkComplex($key, ConnectionInterface $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     *
     * <code>$c->findPks(array(12, 56, 832), $con);</code>
     *
     * @param array $keys Primary keys to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con an optional connection object
     *
     * @return \Propel\Runtime\Collection\Collection<\ChurchCRM\Plugins\Sentinel\Model\Base\SentinelRecord>|mixed|array the list of results, formatted by the current formatter
     */
    public function findPks($keys, ?ConnectionInterface $con = null)
    {
        if (!$con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param mixed $key Primary key to use for the query
     *
     * @return $this
     */
    public function filterByPrimaryKey($key)
    {
        $resolvedColumn = $this->resolveLocalColumnByName('record_key');
        $this->addUsingOperator($resolvedColumn, $key, Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param array $keys The list of primary key values to use for the query
     *
     * @return static
     */
    public function filterByPrimaryKeys(array $keys)
    {
        $resolvedColumn = $this->resolveLocalColumnByName('record_key');
        $this->addUsingOperator($resolvedColumn, $keys, Criteria::IN);

        return $this;
    }

    /**
     * Filter the query on the record_key column
     *
     * Example usage:
     * <code>
     * $query->filterByRecordKey('fooValue'); // WHERE record_key = 'fooValue'
     * $query->filterByRecordKey('%fooValue%', Criteria::LIKE); // WHERE record_key LIKE '%fooValue%'
     * $query->filterByRecordKey(['foo', 'bar']); // WHERE record_key IN ('foo', 'bar')
     * </code>
     *
     * @param array<string>|string|null $recordKey The value to use as filter.
     * @param string|null $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this
     */
    public function filterByRecordKey($recordKey = null, ?string $comparison = null)
    {
        $resolvedColumn = $this->resolveLocalColumnByName('record_key');
        if ($comparison === null && is_array($recordKey)) {
            $comparison = Criteria::IN;
        }
        $this->addUsingOperator($resolvedColumn, $recordKey, $comparison);

        return $this;
    }

    /**
     * Filter the query on the kind column
     *
     * Example usage:
     * <code>
     * $query->filterByKind('fooValue'); // WHERE kind = 'fooValue'
     * $query->filterByKind('%fooValue%', Criteria::LIKE); // WHERE kind LIKE '%fooValue%'
     * $query->filterByKind(['foo', 'bar']); // WHERE kind IN ('foo', 'bar')
     * </code>
     *
     * @param array<string>|string|null $kind The value to use as filter.
     * @param string|null $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this
     */
    public function filterByKind($kind = null, ?string $comparison = null)
    {
        $resolvedColumn = $this->resolveLocalColumnByName('kind');
        if ($comparison === null && is_array($kind)) {
            $comparison = Criteria::IN;
        }
        $this->addUsingOperator($resolvedColumn, $kind, $comparison);

        return $this;
    }

    /**
     * Filter the query on the payload column
     *
     * Example usage:
     * <code>
     * $query->filterByPayload('fooValue'); // WHERE payload = 'fooValue'
     * $query->filterByPayload('%fooValue%', Criteria::LIKE); // WHERE payload LIKE '%fooValue%'
     * $query->filterByPayload(['foo', 'bar']); // WHERE payload IN ('foo', 'bar')
     * </code>
     *
     * @param array<string>|string|null $payload The value to use as filter.
     * @param string|null $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this
     */
    public function filterByPayload($payload = null, ?string $comparison = null)
    {
        $resolvedColumn = $this->resolveLocalColumnByName('payload');
        if ($comparison === null && is_array($payload)) {
            $comparison = Criteria::IN;
        }
        $this->addUsingOperator($resolvedColumn, $payload, $comparison);

        return $this;
    }

    /**
     * Filter the query on the revision column
     *
     * Example usage:
     * <code>
     * $query->filterByRevision(1234); // WHERE revision = 1234
     * $query->filterByRevision(array(12, 34)); // WHERE revision IN (12, 34)
     * $query->filterByRevision(array('min' => 12)); // WHERE revision > 12
     * </code>
     *
     * @param mixed $revision The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param string|null $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this
     */
    public function filterByRevision($revision = null, ?string $comparison = null)
    {
        $resolvedColumn = $this->resolveLocalColumnByName('revision');
        if (is_array($revision)) {
            $useMinMax = false;
            if (isset($revision['min'])) {
                $this->addUsingOperator($resolvedColumn, $revision['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($revision['max'])) {
                $this->addUsingOperator($resolvedColumn, $revision['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if ($comparison === null) {
                $comparison = Criteria::IN;
            }
        }
        $this->addUsingOperator($resolvedColumn, $revision, $comparison);

        return $this;
    }

    /**
     * Exclude object from result
     *
     * @param \ChurchCRM\Plugins\Sentinel\Model\SentinelRecord|null $sentinelRecord Object to remove from the list of results
     *
     * @return $this
     */
    public function prune(?ChildSentinelRecord $sentinelRecord = null)
    {
        if (!$sentinelRecord) {
            return $this;
        }

        $this
            ->combineFilters(Criteria::LOGICAL_OR)
            ->addUsingOperator($this->resolveLocalColumnByName('record_key'), $sentinelRecord->getRecordKey(), Criteria::NOT_EQUAL)
            ->endCombineFilters();

        return $this;
    }

    /**
     * Deletes all rows from the sentinel_record table.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con the connection to use
     *
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(?ConnectionInterface $con = null): int
    {
        if (!$con) {
            $con = Propel::getServiceContainer()->getWriteConnection(SentinelRecordTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0;
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            SentinelRecordTableMap::clearInstancePool();
            SentinelRecordTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

    /**
     * Performs a DELETE on the database based on the current ModelCriteria
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con the connection to use
     *
     * @return int The number of affected rows (if supported by underlying database driver). This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     */
    public function delete(?ConnectionInterface $con = null): int
    {
        if (!$con) {
            $con = Propel::getServiceContainer()->getWriteConnection(SentinelRecordTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(SentinelRecordTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0;
            SentinelRecordTableMap::removeInstanceFromPool($criteria);
            $affectedRows += ModelCriteria::delete($con);
            SentinelRecordTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }
}
