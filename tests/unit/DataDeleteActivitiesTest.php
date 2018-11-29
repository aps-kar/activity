<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Activity\Tests\Unit;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use OCA\Activity\Data;
use OCP\Activity\IExtension;

/**
 * Class DataDeleteActivitiesTest
 *
 * @group DB
 * @package OCA\Activity\Tests
 */
class DataDeleteActivitiesTest extends TestCase {
	/** @var \OCA\Activity\Data */
	protected $data;

	protected function setUp() {
		parent::setUp();

		$activities = [
			['affectedUser' => 'delete', 'subject' => 'subject', 'time' => 0],
			['affectedUser' => 'delete', 'subject' => 'subject2', 'time' => \time() - 2 * 365 * 24 * 3600],
			['affectedUser' => 'otherUser', 'subject' => 'subject', 'time' => \time()],
			['affectedUser' => 'otherUser', 'subject' => 'subject2', 'time' => \time()],
		];

		$queryActivity = \OC::$server->getDatabaseConnection()->prepare('INSERT INTO `*PREFIX*activity`(`app`, `subject`, `subjectparams`, `message`, `messageparams`, `file`, `link`, `user`, `affecteduser`, `timestamp`, `priority`, `type`)' . ' VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )');
		foreach ($activities as $activity) {
			$queryActivity->execute([
				'app',
				$activity['subject'],
				\json_encode([]),
				'',
				\json_encode([]),
				'file',
				'link',
				'user',
				$activity['affectedUser'],
				$activity['time'],
				IExtension::PRIORITY_MEDIUM,
				'test',
			]);
		}
		$this->data = new Data(
			$this->createMock('\OCP\Activity\IManager'),
			\OC::$server->getDatabaseConnection(),
			$this->createMock('\OCP\IUserSession')
		);
	}

	protected function tearDown() {
		$this->data->deleteActivities([
			'type' => 'test',
		]);

		parent::tearDown();
	}

	public function deleteActivitiesData() {
		return [
			[['affecteduser' => 'delete'], ['otherUser']],
			[['affecteduser' => ['delete', '=']], ['otherUser']],
			[['timestamp' => [\time() - 10, '<']], ['otherUser']],
			[['timestamp' => [\time() - 10, '>']], ['delete']],
		];
	}

	/**
	 * @dataProvider deleteActivitiesData
	 */
	public function testDeleteActivities($condition, $expected) {
		$this->assertUserActivities(['delete', 'otherUser']);
		$this->data->deleteActivities($condition);
		$this->assertUserActivities($expected);
	}

	public function testExpireActivities() {
		$backgroundjob = new \OCA\Activity\BackgroundJob\ExpireActivities();
		$this->assertUserActivities(['delete', 'otherUser']);
		$jobList = $this->createMock('\OCP\BackgroundJob\IJobList');
		$backgroundjob->execute($jobList);
		$this->assertUserActivities(['otherUser']);
	}

	protected function assertUserActivities($expected) {
		$query = \OC::$server->getDatabaseConnection()->prepare("SELECT `affecteduser` FROM `*PREFIX*activity` WHERE `type` = 'test'");
		$this->assertTableKeys($expected, $query, 'affecteduser');
	}

	protected function assertTableKeys($expected, Statement $query, $keyName) {
		$query->execute();

		$users = [];
		while ($row = $query->fetch()) {
			$users[] = $row[$keyName];
		}
		$query->closeCursor();
		$users = \array_unique($users);
		\sort($users);
		\sort($expected);

		$this->assertEquals($expected, $users);
	}

	public function testChunkingDeleteNotUsedWhenNotOnMysql() {
		$ttl = (60 * 60 * 24 * \max(1, 365));
		$timelimit = \time() - $ttl;
		$activityManager = $this->createMock(\OCP\Activity\IManager::class);
		$connection = $this->createMock(\OCP\IDBConnection::class);
		$platform = $this->createMock(SqlitePlatform::class);
		$connection->expects($this->once())->method('getDatabasePlatform')->willReturn($platform);

		$statement = $this->createMock(Statement::class);
		// Wont chunk
		$statement->expects($this->exactly(0))->method('rowCount')->willReturnOnConsecutiveCalls(100000, 50);
		$connection->expects($this->once())->method('prepare')->willReturn($statement);

		$userSession = $this->createMock(\OCP\IUserSession::class);
		$data = new Data($activityManager, $connection, $userSession);
		$data->deleteActivities([
			'timestamp' => [$timelimit, '<'],
		]);
	}

	public function testDeleteActivitiesIsChunkedOnMysql() {
		$ttl = (60 * 60 * 24 * \max(1, 365));
		$timelimit = \time() - $ttl;
		$activityManager = $this->createMock(\OCP\Activity\IManager::class);
		$connection = $this->createMock(\OCP\IDBConnection::class);
		$platform = $this->createMock(MySqlPlatform::class);
		$connection->expects($this->once())->method('getDatabasePlatform')->willReturn($platform);

		$statement = $this->createMock(Statement::class);
		// Will chunk
		$statement->expects($this->exactly(2))->method('rowCount')->willReturnOnConsecutiveCalls(100000, 50);
		$connection->expects($this->once())->method('prepare')->willReturn($statement);

		$userSession = $this->createMock(\OCP\IUserSession::class);
		$data = new Data($activityManager, $connection, $userSession);
		$data->deleteActivities([
			'timestamp' => [$timelimit, '<'],
		]);
	}
}
