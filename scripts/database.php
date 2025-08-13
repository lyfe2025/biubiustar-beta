<?php
/**
 * 共用数据库连接脚本
 * 用于从.env文件读取配置并建立数据库连接
 * 日期：2025-01-11
 */

class Database 
{
    private static $instance = null;
    private $pdo = null;
    private $config = [];
    
    /**
     * 私有构造函数，防止直接实例化
     */
    private function __construct() 
    {
        $this->loadConfig();
        $this->connect();
    }
    
    /**
     * 获取数据库连接实例（单例模式）
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 从.env文件加载数据库配置
     */
    private function loadConfig()
    {
        $envFile = $this->findEnvFile();
        if (!$envFile) {
            throw new Exception("找不到.env文件，请确保在项目根目录运行");
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, '[')) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        
        // 设置默认值
        $this->config = [
            'hostname' => $config['hostname'] ?? '127.0.0.1',
            'hostport' => $config['hostport'] ?? '3306',
            'database' => $config['database'] ?? 'biubiustar',
            'username' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
            'prefix' => $config['prefix'] ?? 'fa_',
            'charset' => $config['charset'] ?? 'utf8mb4'
        ];
    }
    
    /**
     * 查找.env文件
     */
    private function findEnvFile()
    {
        $currentDir = getcwd();
        $maxLevels = 5; // 最多向上查找5级目录
        
        for ($i = 0; $i < $maxLevels; $i++) {
            $envFile = $currentDir . '/.env';
            if (file_exists($envFile)) {
                return $envFile;
            }
            
            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) {
                break; // 已到根目录
            }
            $currentDir = $parentDir;
        }
        
        return false;
    }
    
    /**
     * 建立数据库连接
     */
    private function connect()
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $this->config['hostname'],
                $this->config['hostport'],
                $this->config['database'],
                $this->config['charset']
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->config['charset']
            ];
            
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );
            
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取PDO连接对象
     * @return PDO
     */
    public function getPDO()
    {
        return $this->pdo;
    }
    
    /**
     * 获取数据库配置
     * @param string $key 配置键名，为空则返回所有配置
     * @return mixed
     */
    public function getConfig($key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? null;
    }
    
    /**
     * 获取表前缀
     * @return string
     */
    public function getPrefix()
    {
        return $this->config['prefix'];
    }
    
    /**
     * 获取完整表名（加上前缀）
     * @param string $tableName 表名
     * @return string
     */
    public function getTableName($tableName)
    {
        return $this->config['prefix'] . $tableName;
    }
    
    /**
     * 执行查询并返回所有结果
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return array
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * 执行查询并返回单行结果
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return array|false
     */
    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * 执行写入操作（INSERT、UPDATE、DELETE）
     * @param string $sql SQL语句
     * @param array $params 参数
     * @return int 影响的行数
     */
    public function execute($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * 获取最后插入的ID
     * @return string
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit()
    {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * 检查是否在事务中
     * @return bool
     */
    public function inTransaction()
    {
        return $this->pdo->inTransaction();
    }
    
    /**
     * 测试数据库连接
     * @return array 连接信息
     */
    public function testConnection()
    {
        try {
            $result = $this->fetchOne("SELECT VERSION() as version, DATABASE() as db_name, USER() as user");
            return [
                'status' => 'success',
                'message' => '数据库连接正常',
                'version' => $result['version'],
                'database' => $result['db_name'],
                'user' => $result['user'],
                'config' => [
                    'hostname' => $this->config['hostname'],
                    'hostport' => $this->config['hostport'],
                    'database' => $this->config['database'],
                    'username' => $this->config['username'],
                    'prefix' => $this->config['prefix']
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => '数据库连接失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 显示配置信息（隐藏密码）
     */
    public function showConfig()
    {
        echo "数据库配置信息:\n";
        echo "主机: {$this->config['hostname']}\n";
        echo "端口: {$this->config['hostport']}\n";
        echo "数据库: {$this->config['database']}\n";
        echo "用户名: {$this->config['username']}\n";
        echo "密码: " . str_repeat('*', strlen($this->config['password'])) . "\n";
        echo "表前缀: {$this->config['prefix']}\n";
        echo "字符集: {$this->config['charset']}\n";
    }
    
    /**
     * 防止克隆
     */
    private function __clone() {}
    
    /**
     * 防止序列化
     */
    public function __sleep()
    {
        throw new Exception('不允许序列化数据库连接对象');
    }
    
    /**
     * 防止反序列化
     */
    public function __wakeup()
    {
        throw new Exception('不允许反序列化数据库连接对象');
    }
}

/**
 * 快速获取数据库连接的便捷函数
 * @return Database
 */
function db()
{
    return Database::getInstance();
}

/**
 * 快速获取PDO连接的便捷函数
 * @return PDO
 */
function pdo()
{
    return Database::getInstance()->getPDO();
}

/**
 * 快速获取表名的便捷函数
 * @param string $tableName 表名
 * @return string 完整表名
 */
function table($tableName)
{
    return Database::getInstance()->getTableName($tableName);
}

// 如果是直接运行此文件，则执行测试
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "数据库连接测试\n";
    echo "================\n\n";
    
    try {
        $db = Database::getInstance();
        $db->showConfig();
        echo "\n";
        
        $result = $db->testConnection();
        if ($result['status'] === 'success') {
            echo "✅ " . $result['message'] . "\n";
            echo "MySQL版本: " . $result['version'] . "\n";
            echo "当前数据库: " . $result['database'] . "\n";
            echo "当前用户: " . $result['user'] . "\n";
        } else {
            echo "❌ " . $result['message'] . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 错误: " . $e->getMessage() . "\n";
    }
}
?>