<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */
namespace Doctrine\Tests\DBAL\Sharding;

use Doctrine\DBAL\Sharding\PoolingShardManager;

class PoolingShardManagerTest extends \PHPUnit\Framework\TestCase
{
    private function createConnectionMock()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Sharding\PoolingShardConnection')
            ->setMethods(array('connect', 'getParams', 'fetchAll'))
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createPassthroughShardChoser()
    {
        $mock = $this->createMock('Doctrine\DBAL\Sharding\ShardChoser\ShardChoser');
        $mock->expects($this->any())
             ->method('pickShard')
             ->will($this->returnCallback(function($value) { return $value; }));
        return $mock;
    }

    private function createStaticShardChoser()
    {
        $mock = $this->createMock('Doctrine\DBAL\Sharding\ShardChoser\ShardChoser');
        $mock->expects($this->any())
            ->method('pickShard')
            ->will($this->returnCallback(function($value) { return 1; }));
        return $mock;
    }

    public function testSelectGlobal()
    {
        $conn = $this->createConnectionMock();
        $conn->expects($this->once())->method('connect')->with($this->equalTo(0));

        $shardManager = new PoolingShardManager($conn);
        $shardManager->selectGlobal();

        self::assertNull($shardManager->getCurrentDistributionValue());
    }

    public function testSelectShard()
    {
        $shardId = 10;
        $conn = $this->createConnectionMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue(array('shardChoser' => $this->createPassthroughShardChoser())));
        $conn->expects($this->at(1))->method('connect')->with($this->equalTo($shardId));

        $shardManager = new PoolingShardManager($conn);
        $shardManager->selectShard($shardId);

        self::assertEquals($shardId, $shardManager->getCurrentDistributionValue());
    }

    public function testGetShards()
    {
        $conn = $this->createConnectionMock();
        $conn->expects($this->any())->method('getParams')->will(
            $this->returnValue(
                array('shards' => array( array('id' => 1), array('id' => 2) ), 'shardChoser' => $this->createPassthroughShardChoser())
            )
        );

        $shardManager = new PoolingShardManager($conn);
        $shards = $shardManager->getShards();

        self::assertEquals(array(array('id' => 1), array('id' => 2)), $shards);
    }

    public function testQueryAll()
    {
        $sql = "SELECT * FROM table";
        $params = array(1);
        $types = array(1);

        $conn = $this->createConnectionMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue(
            array('shards' => array( array('id' => 1), array('id' => 2) ), 'shardChoser' => $this->createPassthroughShardChoser())
        ));
        $conn->expects($this->at(1))->method('getParams')->will($this->returnValue(
            array('shards' => array( array('id' => 1), array('id' => 2) ), 'shardChoser' => $this->createPassthroughShardChoser())
        ));
        $conn->expects($this->at(2))->method('connect')->with($this->equalTo(1));
        $conn->expects($this->at(3))
             ->method('fetchAll')
             ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
             ->will($this->returnValue(array( array('id' => 1) ) ));
        $conn->expects($this->at(4))->method('connect')->with($this->equalTo(2));
        $conn->expects($this->at(5))
             ->method('fetchAll')
             ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
             ->will($this->returnValue(array( array('id' => 2) ) ));

        $shardManager = new PoolingShardManager($conn);
        $result = $shardManager->queryAll($sql, $params, $types);

        self::assertEquals(array(array('id' => 1), array('id' => 2)), $result);
    }

    public function testQueryAllWithStaticShardChoser()
    {
        $sql = "SELECT * FROM table";
        $params = array(1);
        $types = array(1);

        $conn = $this->createConnectionMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue(
            array('shards' => array( array('id' => 1), array('id' => 2) ), 'shardChoser' => $this->createStaticShardChoser())
        ));
        $conn->expects($this->at(1))->method('getParams')->will($this->returnValue(
            array('shards' => array( array('id' => 1), array('id' => 2) ), 'shardChoser' => $this->createStaticShardChoser())
        ));
        $conn->expects($this->at(2))->method('connect')->with($this->equalTo(1));
        $conn->expects($this->at(3))
            ->method('fetchAll')
            ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
            ->will($this->returnValue(array( array('id' => 1) ) ));
        $conn->expects($this->at(4))->method('connect')->with($this->equalTo(2));
        $conn->expects($this->at(5))
            ->method('fetchAll')
            ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
            ->will($this->returnValue(array( array('id' => 2) ) ));

        $shardManager = new PoolingShardManager($conn);
        $result = $shardManager->queryAll($sql, $params, $types);

        self::assertEquals(array(array('id' => 1), array('id' => 2)), $result);
    }
}
