/**
 *
 * @param cards
 * @param otherLabel
 */
export function groupCardsByProject(cards, otherLabel = 'Other') {
	const groups = new Map()

	for (const card of cards) {
		const project = card.project
		const key = project?.id ?? '__other__'
		const projectName = project?.name ?? otherLabel
		const boardId = card.boardId ?? null

		if (!groups.has(key)) {
			groups.set(key, {
				key,
				project,
				projectName,
				boardId,
				cards: [],
			})
		}
		groups.get(key).cards.push(card)
	}

	return [...groups.values()].sort((a, b) => {
		if (a.key === '__other__') return 1
		if (b.key === '__other__') return -1
		return a.projectName.localeCompare(b.projectName)
	})
}
