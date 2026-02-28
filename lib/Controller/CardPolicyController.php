<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\CardPolicyService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class CardPolicyController extends OCSController
{
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly CardPolicyService $cardPolicyService,
		private readonly CardMapper $cardMapper,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(int $boardId): DataResponse
	{
		try {
			$state = $this->cardPolicyService->getBoardPolicyState($boardId);
			$explicit = $this->cardPolicyService->getExplicitCardPoliciesByBoard($boardId);
			$defaults = $state['defaultRoleKeys'] ?? ['move' => [], 'approve' => [], 'view' => []];

			// Cards (raw query: QBMapper::findEntities is protected)
			$cardsQb = $this->cardMapper->queryCardsByBoard($boardId);
			$cardsQb->andWhere($cardsQb->expr()->eq('c.archived', $cardsQb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
				->andWhere($cardsQb->expr()->eq('c.deleted_at', $cardsQb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
				->orderBy('c.last_modified', 'DESC');
			$result = $cardsQb->executeQuery();
			$cardItems = [];
			while ($row = $result->fetch()) {
				$cardId = (int) ($row['id'] ?? 0);
				if ($cardId <= 0) {
					continue;
				}
				$explicitPolicy = $explicit[$cardId] ?? null;
				$effectiveMove = $explicitPolicy['move'] ?? ($defaults['move'] ?? []);
				$effectiveApprove = $explicitPolicy['approve'] ?? ($defaults['approve'] ?? []);
				$explicitView = $explicitPolicy['view'] ?? [];
				$defaultView = $defaults['view'] ?? [];
				$effectiveView = $explicitView !== []
					? $explicitView
					: ($defaultView !== [] ? $defaultView : array_values(array_unique(array_merge($effectiveMove, $effectiveApprove))));
				$cardItems[] = [
					'id' => $cardId,
					'title' => (string) ($row['title'] ?? ''),
					'stackId' => (int) ($row['stack_id'] ?? 0),
					'done' => isset($row['done']) && $row['done'] instanceof \DateTimeInterface ? $row['done']->format(DATE_ATOM) : (is_string($row['done'] ?? null) ? (string) $row['done'] : null),
					'hasExplicitPolicy' => $explicitPolicy !== null,
					'policy' => [
						'move' => $explicitPolicy['move'] ?? [],
						'approve' => $explicitPolicy['approve'] ?? [],
						'view' => $explicitPolicy['view'] ?? [],
					],
					'effectivePolicy' => [
						'move' => $effectiveMove,
						'approve' => $effectiveApprove,
						'view' => $effectiveView,
					],
				];
			}
			$result->closeCursor();

			$state['cards'] = $cardItems;
			return new DataResponse($state);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			$this->logger->error('Error fetching card policy state: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function enable(int $boardId): DataResponse
	{
		try {
			$this->cardPolicyService->enableCardPolicyMode($boardId);
			return new DataResponse(['success' => true]);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			$this->logger->error('Error enabling card policy: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateSettings(int $boardId, ?int $approvedStackId = null): DataResponse
	{
		try {
			if ($approvedStackId !== null) {
				$this->cardPolicyService->setApprovedStackId($boardId, $approvedStackId);
			}
			return new DataResponse(['success' => true]);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (BadRequestException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('Error updating card policy settings: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateDefaults(int $boardId, array $move = [], array $approve = [], array $view = []): DataResponse
	{
		try {
			$this->cardPolicyService->setBoardDefaultRolesByKeys($boardId, $move, $approve, $view);
			return new DataResponse(['success' => true]);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (BadRequestException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('Error updating card policy defaults: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function setCardPolicy(int $boardId, int $cardId, array $move = [], array $approve = [], array $view = []): DataResponse
	{
		try {
			$this->cardPolicyService->setCardPolicyByRoleKeys($boardId, $cardId, $move, $approve, $view);
			return new DataResponse(['success' => true], Http::STATUS_OK);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (BadRequestException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('Error setting card policy: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function clearCardPolicy(int $boardId, int $cardId): DataResponse
	{
		try {
			$this->cardPolicyService->clearCardPolicy($boardId, $cardId);
			return new DataResponse(['success' => true], Http::STATUS_OK);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			$this->logger->error('Error clearing card policy: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function addMembership(int $boardId, string $roleKey, string $participant, int $participantType): DataResponse
	{
		try {
			$membership = $this->cardPolicyService->addMembership($boardId, $roleKey, $participant, $participantType);
			return new DataResponse($membership, Http::STATUS_CREATED);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (BadRequestException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('Error adding membership: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteMembership(int $boardId, int $membershipId): DataResponse
	{
		try {
			$this->cardPolicyService->deleteMembership($boardId, $membershipId);
			return new DataResponse([], Http::STATUS_NO_CONTENT);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			$this->logger->error('Error deleting membership: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function createRole(int $boardId, string $roleKey, string $name, string $color = '#000000'): DataResponse
	{
		try {
			$role = $this->cardPolicyService->createRole($boardId, $roleKey, $name, $color);
			return new DataResponse($role, Http::STATUS_CREATED);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (BadRequestException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('Error creating role: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateRole(int $boardId, int $roleId, ?string $name = null, ?string $color = null): DataResponse
	{
		try {
			$role = $this->cardPolicyService->updateRole($boardId, $roleId, $name, $color);
			return new DataResponse($role, Http::STATUS_OK);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (BadRequestException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('Error updating role: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteRole(int $boardId, int $roleId): DataResponse
	{
		try {
			$this->cardPolicyService->deleteRole($boardId, $roleId);
			return new DataResponse([], Http::STATUS_NO_CONTENT);
		} catch (NoPermissionException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (BadRequestException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			$this->logger->error('Error deleting role: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
