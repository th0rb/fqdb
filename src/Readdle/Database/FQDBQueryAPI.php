<?php
namespace Readdle\Database;


class FQDBQueryAPI extends FQDBExecutor {


    /**
     * executes prepared \PDO query
     * @param string $query
     * @param array $options
     * @return \PDOStatement PDO statement from query
     */
    private function _runQuery($query, $options)
    {
        $this->_testQueryStarts($query, '[select|show]');
        $statement = $this->_executeQuery($query, $options);
        if (is_object($statement) && $statement instanceof \PDOStatement) {
            return $statement;
        }
        else {
            $this->_error(FQDBException::INTERNAL_ASSERTION_FAIL, FQDBException::FQDB_CODE);
        }
    }


    /**
     * executes SELECT or SHOW query and returns result
     * @param string $query
     * @param array $options
     * @param callable $fetcher
     * @param bool $returnArray
     * @return array|string|false
     */
    private function queryOrFalse($query, $options, $fetcher, $returnArray = true) {
        $statement = $this->_runQuery($query, $options);

        $result = call_user_func($fetcher, $statement);

        if (!is_array($result) || count($result) == 0) {
            return false;
        }
        else {
            return $returnArray ? $result : reset($result);
        }
    }


    /**
     * executes SELECT or SHOW query and returns 1st returned element
     * @param string $query
     * @param array $options
     * @return mixed
     */
    public function queryValue($query, $options = array())
    {
        return $this->queryOrFalse($query, $options,
            function(\PDOStatement $statement) { return $statement->fetch(\PDO::FETCH_NUM); },
            false);
    }

    /**
     * executes SELECT or SHOW query and returns 1st row as assoc array
     * @param string $query
     * @param array $options
     * @return array|false
     */
    public function queryAssoc($query, $options = array())
    {
        $statement = $this->_runQuery($query, $options);
        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * executes SELECT or SHOW query and returns as array
     * @param string $query
     * @param array $options
     * @return array|false
     */
    public function queryList($query, $options = array())
    {
        $statement = $this->_runQuery($query, $options);
        return $statement->fetch(\PDO::FETCH_NUM);
    }



    /**
     * executes SELECT or SHOW query and returns result as array
     * @param string $query
     * @param array $options
     * @return array|false
     */
    public function queryVector($query, $options = array())
    {
        return $this->queryOrFalse($query, $options,
            function(\PDOStatement $statement) { return $statement->fetchAll(\PDO::FETCH_COLUMN, 0); },
            true
        );

    }

    /**
     * executes SELECT or SHOW query and returns result as assoc array
     * @param string $query
     * @param array $options
     * @return array|false
     */
    public function queryTable($query, $options = array())
    {
        return $this->queryOrFalse($query, $options,
            function(\PDOStatement $statement) { return $statement->fetchAll(\PDO::FETCH_ASSOC); },
            true
        );
    }

    /**
     * executes SELECT or SHOW query and returns result as array of objects of given class
     * @param string $query
     * @param string $className
     * @param array $options
     * @param array $classConstructorArguments
     * @return array|false
     */
    public function queryObjArray($query, $className, $options = array(), $classConstructorArguments = NULL)
    {
        if (!class_exists($className)) {
            $this->_error(FQDBException::CLASS_NOT_EXIST, FQDBException::FQDB_CODE);
        }

        return $this->queryOrFalse($query, $options,
            function(\PDOStatement $statement) use ($className, $classConstructorArguments) {
                return $statement->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE,
                    $className, $classConstructorArguments);
            },
            true
        );
    }


    /**
     * executes SELECT or SHOW query and returns object of given class
     * @param string $query
     * @param string $className
     * @param array $options
     * @param array $classConstructorArguments
     * @return object|false
     */
    public function queryObj($query, $className, $options = array(), $classConstructorArguments = NULL)
    {
        if (!class_exists($className)) {
            $this->_error(FQDBException::CLASS_NOT_EXIST, FQDBException::FQDB_CODE);
        }

        $statement = $this->_runQuery($query, $options);
        $statement->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $className, $classConstructorArguments);

        return $statement->fetch();
    }

    /**
     * Execute query and apply a callback function to each row
     *
     * @param string $query
     * @param array $options
     * @param callable $callback
     * @return boolean
     */
    public function queryTableCallback($query, $options = [], $callback)
    {
        if(!is_callable($callback)) {
            $this->_error(FQDBException::NOT_CALLABLE_ERROR, FQDBException::FQDB_CODE);
        }
        $statement = $this->_runQuery($query, $options);
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            call_user_func($callback, $row);
        }
        return true; //executed successfully
    }


}