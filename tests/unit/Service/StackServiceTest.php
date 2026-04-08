<?php

/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Validators\StackServiceValidator;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Class StackServiceTest
 *
 * @package OCA\Deck\Service
 * @group DB
 */
class StackServiceTest extends TestCase {

	/** @var StackService */
	private $stackService;
	/** @var \PHPUnit\Framework\MockObject\MockObject|StackMapper */
	private $stackMapper;
	/** @var \PHPUnit\Framework\MockObject\MockObject|CardMapper */
	private $cardMapper;
	/** @var \PHPUnit\Framework\MockObject\MockObject|BoardMapper */
	private $boardMapper;
	/** @var \PHPUnit\Framework\MockObject\MockObject|LabelMapper */
	private $labelMapper;
	/** @var \PHPUnit\Framework\MockObject\MockObject|PermissionService */
	private $permissionService;
	/** @var AssignmentMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $assignedUsersMapper;
	/** @var AttachmentService|\PHPUnit\Framework\MockObject\MockObject */
	private $attachmentService;
	/** @var BoardService|\PHPUnit\Framework\MockObject\MockObject */
	private $boardService;
	/** @var CardService|\PHPUnit\Framework\MockObject\MockObject */
	private $cardService;
	/** @var ActivityManager|\PHPUnit\Framework\MockObject\MockObject */
	private $activityManager;
	/** @var ChangeHelper|\PHPUnit\Framework\MockObject\MockObject */
	private $changeHelper;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var IEventDispatcher|\PHPUnit\Framework\MockObject\MockObject */
	private $eventDispatcher;
	/** @var StackServiceValidator|\PHPUnit\Framework\MockObject\MockObject */
	private $stackServiceValidator;
	/** @var ProjectMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $projectMapper;

	public function setUp(): void {
		parent::setUp();
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->cardService = $this->createMock(CardService::class);
		$this->assignedUsersMapper = $this->createMock(AssignmentMapper::class);
		$this->attachmentService = $this->createMock(AttachmentService::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->stackServiceValidator = $this->createMock(StackServiceValidator::class);
		$this->projectMapper = $this->createMock(ProjectMapper::class);

		$this->stackService = new StackService(
			$this->stackMapper,
			$this->boardMapper,
			$this->cardMapper,
			$this->labelMapper,
			$this->permissionService,
			$this->boardService,
			$this->cardService,
			$this->assignedUsersMapper,
			$this->attachmentService,
			$this->activityManager,
			$this->changeHelper,
			$this->logger,
			$this->eventDispatcher,
			$this->stackServiceValidator,
			$this->projectMapper
		);
	}

	public function testFindAll() {
		$this->permissionService->expects($this->once())->method('checkPermission');
		$this->stackMapper->expects($this->once())->method('findAll')->willReturn($this->getStacks());
		$board = new \OCA\Deck\Db\Board();
		$board->setOwner('user');
		$this->boardMapper->expects($this->atLeastOnce())->method('find')->willReturn($board);
		$this->cardService->expects($this->atLeastOnce())->method('enrichCards')->will(
			$this->returnCallback(
				function ($cards) {
					foreach ($cards as $card) {
						$card->setLabels($this->getLabels()[$card->getId()]);
					}
					return array_map(function ($card) {
						return new CardDetails($card);
					}, $cards);
				}
			)
		);
		$this->cardMapper->expects($this->any())->method('findAll')->willReturn($this->getCards(222));


		$actual = $this->stackService->findAll('user', '123');
		for ($stackId = 0; $stackId < 3; $stackId++) {
			for ($cardId = 0;$cardId < 10;$cardId++) {
				$this->assertEquals($actual[0]->getCards()[$cardId]->getId(), $cardId);
				$this->assertEquals($actual[0]->getCards()[$cardId]->getStackId(), 222);
				$this->assertEquals($actual[0]->getCards()[$cardId]->getLabels(), $this->getLabels()[$cardId]);
			}
		}
	}

	public function testFindAllReturnsOwnedCardsForNonOwner() {
		$this->permissionService->expects($this->once())->method('checkPermission');
		$this->stackMapper->expects($this->once())->method('findAll')->willReturn($this->getStacks());
		$board = new \OCA\Deck\Db\Board();
		$board->setOwner('board-owner');
		$this->boardMapper->expects($this->atLeastOnce())->method('find')->willReturn($board);
		$this->cardMapper->expects($this->atLeastOnce())
			->method('findOwnedOrAssignedToUserInStack')
			->willReturn($this->getCards(222));
		$this->cardService->expects($this->atLeastOnce())->method('enrichCards')->will(
			$this->returnCallback(function ($cards) {
				return array_map(function ($card) {
					return new CardDetails($card);
				}, $cards);
			})
		);

		$actual = $this->stackService->findAll('user', '123');
		$this->assertCount(2, $actual);
		$this->assertNotEmpty($actual[0]->getCards());
	}

	public function testFindAllArchived() {
		$this->permissionService->expects($this->once())->method('checkPermission');
		$this->stackMapper->expects($this->once())->method('findAll')->willReturn($this->getStacks());
		$this->labelMapper->expects($this->once())->method('getAssignedLabelsForBoard')->willReturn($this->getLabels());
		$this->cardMapper->expects($this->any())->method('findAllArchived')->willReturn($this->getCards(222));

		$actual = $this->stackService->findAllArchived(123);
		for ($stackId = 0; $stackId < 3; $stackId++) {
			for ($cardId = 0;$cardId < 10;$cardId++) {
				$this->assertEquals($actual[0]->getCards()[$cardId]->getId(), $cardId);
				$this->assertEquals($actual[0]->getCards()[$cardId]->getStackId(), 222);
				$this->assertEquals($actual[0]->getCards()[$cardId]->getLabels(), $this->getLabels()[$cardId]);
			}
		}
	}

	private function getLabels() {
		for ($i = 0;$i < 10;$i++) {
			$label1 = new Label();
			$label1->setTitle('Important');
			$label1->setCardId(1);
			$label2 = new Label();
			$label2->setTitle('Maybe');
			$label2->setCardId(2);
			$labels[$i] = [
				$label1,
				$label2
			];
		}
		return $labels;
	}
	private function getStacks() {
		$s1 = new Stack();
		$s1->setId(222);
		$s1->setBoardId(1);
		$s2 = new Stack();
		$s2->setId(223);
		$s1->setBoardId(1);
		return [$s1, $s2];
	}
	private function getCards($stackId = 0) {
		$cards = [];
		for ($i = 0;$i < 10;$i++) {
			$cards[$i] = new Card();
			$cards[$i]->setId($i);
			$cards[$i]->setStackId($stackId);
		}
		return $cards;
	}

	public function testCreate() {
		$this->permissionService->expects($this->once())->method('checkPermission');
		$this->projectMapper->expects($this->once())
			->method('findByBoardId')
			->with(2)
			->willReturn(null);
		$stack = new Stack();
		$stack->setId(123);
		$stack->setTitle('Foo');
		$stack->setBoardId(2);
		$stack->setOrder(1);
		$this->stackMapper->expects($this->once())->method('insert')->willReturn($stack);
		$result = $this->stackService->create('Foo', 2, 1);
		$this->assertEquals($stack, $result);
	}

	public function testCreateRejectsCombiProjectBoards(): void {
		$this->expectException(NoPermissionException::class);
		$this->permissionService->expects($this->once())->method('checkPermission');
		$this->projectMapper->expects($this->once())
			->method('findByBoardId')
			->with(2)
			->willReturn($this->createProject(0));
		$this->stackMapper->expects($this->never())->method('insert');

		$this->stackService->create('Foo', 2, 1);
	}

	public function testDelete() {
		$this->permissionService->expects($this->once())->method('checkPermission');
		$stackToBeDeleted = new Stack();
		$stackToBeDeleted->setId(1);
		$stackToBeDeleted->setBoardId(1);
		$this->projectMapper->expects($this->once())
			->method('findByBoardId')
			->with(1)
			->willReturn(null);
		$this->stackMapper->expects($this->once())->method('find')->willReturn($stackToBeDeleted);
		$this->stackMapper->expects($this->once())->method('update')->willReturn($stackToBeDeleted);
		$this->cardMapper->expects($this->once())->method('findAll')->willReturn([]);
		$this->stackService->delete(123);
		$this->assertTrue($stackToBeDeleted->getDeletedAt() <= time(), 'deletedAt is in the past');
		$this->assertTrue($stackToBeDeleted->getDeletedAt() > 0, 'deletedAt is set');
	}

	public function testDeleteRejectsCombiProjectBoards(): void {
		$this->expectException(NoPermissionException::class);
		$this->permissionService->expects($this->once())->method('checkPermission');
		$stack = new Stack();
		$stack->setId(123);
		$stack->setBoardId(1);
		$this->stackMapper->expects($this->once())->method('find')->with(123)->willReturn($stack);
		$this->projectMapper->expects($this->once())
			->method('findByBoardId')
			->with(1)
			->willReturn($this->createProject(0));
		$this->stackMapper->expects($this->never())->method('update');

		$this->stackService->delete(123);
	}

	public function testUpdate() {
		$this->permissionService->expects($this->exactly(2))->method('checkPermission');
		$this->projectMapper->expects($this->once())
			->method('findByBoardId')
			->with(2)
			->willReturn(null);
		$stack = new Stack();
		$this->stackMapper->expects($this->once())->method('find')->willReturn($stack);
		$this->stackMapper->expects($this->once())->method('update')->willReturn($stack);
		$stack->setId(123);
		$stack->setTitle('Foo');
		$stack->setBoardId(2);
		$stack->setOrder(1);
		$result = $this->stackService->update(123, 'Foo', 2, 1, null);
		$this->assertEquals($stack, $result);
	}

	public function testUpdateRejectsCombiProjectBoards(): void {
		$this->expectException(NoPermissionException::class);
		$this->permissionService->expects($this->exactly(2))->method('checkPermission');
		$this->projectMapper->expects($this->once())
			->method('findByBoardId')
			->with(2)
			->willReturn($this->createProject(0));
		$this->stackMapper->expects($this->never())->method('find');

		$this->stackService->update(123, 'Foo', 2, 1, null);
	}

	/**
	 * @group DB
	 */
	public function testReorder() {
		$this->permissionService->expects($this->once())->method('checkPermission');
		$a = $this->createStack(1, 0);
		$b = $this->createStack(2, 1);
		$c = $this->createStack(3, 2);
		$stacks = [$a, $b, $c];
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($a);
		$this->projectMapper->expects($this->once())
			->method('findByBoardId')
			->with(1)
			->willReturn(null);
		$this->stackMapper->expects($this->once())
			->method('findAll')
			->willReturn($stacks);
		$actual = $this->stackService->reorder(1, 2);
		$a = $this->createStack(1, 2);
		$b = $this->createStack(2, 0);
		$c = $this->createStack(3, 1);
		$expected = [$b, $c, $a];
		$this->assertEquals($expected, $actual);
	}

	public function testReorderRejectsCombiProjectBoards(): void {
		$this->expectException(NoPermissionException::class);
		$this->permissionService->expects($this->once())->method('checkPermission');
		$stack = $this->createStack(1, 0);
		$this->stackMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($stack);
		$this->projectMapper->expects($this->once())
			->method('findByBoardId')
			->with(1)
			->willReturn($this->createProject(0));
		$this->stackMapper->expects($this->never())->method('findAll');

		$this->stackService->reorder(1, 2);
	}

	private function createStack($id, $order) {
		$stack = new Stack();
		$stack->setId($id);
		$stack->setBoardId(1);
		$stack->setOrder($order);
		return $stack;
	}

	private function createProject(int $type): Project {
		$project = new Project();
		$project->setType($type);
		return $project;
	}
}
