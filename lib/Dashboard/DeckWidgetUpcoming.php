<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Dashboard;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Service\OverviewService;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;
use OCP\Dashboard\IWidget;

class DeckWidgetUpcoming implements IWidget {
	private IL10N $l10n;
	private OverviewService $dashboardService;
	private IURLGenerator $urlGenerator;
	private IDateTimeFormatter $dateTimeFormatter;

	public function __construct(IL10N $l10n,
		OverviewService $dashboardService,
		IDateTimeFormatter $dateTimeFormatter,
		IURLGenerator $urlGenerator) {
		$this->l10n = $l10n;
		$this->dashboardService = $dashboardService;
		$this->urlGenerator = $urlGenerator;
		$this->dateTimeFormatter = $dateTimeFormatter;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'deckUpcoming';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Upcoming tasks');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-deck';
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'deck-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		Util::addScript('deck', 'deck-dashboard');
	}
}
