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
use OCA\Activity\Data;
use OCA\Activity\Hooks;
use OCP\Activity\IExtension;

/**
 * Class HooksDeleteUserTest
 *
 * @group DB
 * @package OCA\Activity\Tests
 */
class HooksDeleteUserTest extends TestCase {
	protected function setUp() {
		parent::setUp();

		$activities = [
			['affectedUser' => 'delete', 'subject' => 'subject'],
			['affectedUser' => 'delete', 'subject' => 'subject2'],
			['affectedUser' => 'otherUser', 'subject' => 'subject'],
			['affectedUser' => 'otherUser', 'subject' => 'subject2'],
		];

		$queryActivity = \OC::$server->getDatabaseConnection()->prepare('INSERT INTO `*PREFIX*activity`(`app`, `subject`, `subjectparams`, `message`, `messageparams`, `file`, `link`, `user`, `affecteduser`, `timestamp`, `priority`, `type`)' . ' VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )');
		$queryMailQueue = \OC::$server->getDatabaseConnection()->prepare('INSERT INTO `*PREFIX*activity_mq`(`amq_appid`, `amq_subject`, `amq_subjectparams`, `amq_affecteduser`, `amq_timestamp`, `amq_type`, `amq_latest_send`)' . ' VALUES(?, ?, ?, ?, ?, ?, ?)');
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
				\time(),
				IExtension::PRIORITY_MEDIUM,
				'test',
			]);
			$queryMailQueue->execute([
				'app',
				$activity['subject'],
				\json_encode([]),
				$activity['affectedUser'],
				\time(),
				'test',
				\time() + 10,
			]);
		}
	}

	protected function tearDown() {
		$data = new Data(
			$this->createMock('\OCP\Activity\IManager'),
			\OC::$server->getDatabaseConnection(),
			$this->createMock('\OCP\IUserSession')
		);
		$data->deleteActivities([
			'type' => 'test',
		]);
		$query = \OC::$server->getDatabaseConnection()->prepare("DELETE FROM `*PREFIX*activity_mq` WHERE `amq_type` = 'test'");
		$query->execute();

		parent::tearDown();
	}

	public function testHooksDeleteUser() {
		$this->assertUserActivities(['delete', 'otherUser']);
		$this->assertUserMailQueue(['delete', 'otherUser']);
		Hooks::deleteUser(['uid' => 'delete']);
		$this->assertUserActivities(['otherUser']);
		$this->assertUserMailQueue(['otherUser']);
	}

	protected function assertUserActivities($expected) {
		$query = \OC::$server->getDatabaseConnection()->prepare("SELECT `affecteduser` FROM `*PREFIX*activity` WHERE `type` = 'test'");
		$this->assertTableKeys($expected, $query, 'affecteduser');
	}

	protected function assertUserMailQueue($expected) {
		$query = \OC::$server->getDatabaseConnection()->prepare("SELECT `amq_affecteduser` FROM `*PREFIX*activity_mq` WHERE `amq_type` = 'test'");
		$this->assertTableKeys($expected, $query, 'amq_affecteduser');
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
}
