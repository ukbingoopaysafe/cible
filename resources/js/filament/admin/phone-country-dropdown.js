const portals = new Map()

const closeOnFocusLeavingDropdown = (dropdown, container) => {
    const contains = container.contains.bind(container)
    container.contains = (node) => contains(node) || dropdown.contains(node)

    const searchInput = dropdown.querySelector('.fi-select-input-search-ctn .fi-input')

    if (searchInput) {
        const focus = searchInput.focus.bind(searchInput)
        searchInput.focus = (options = {}) => focus({ ...options, preventScroll: true })
    }

    dropdown.addEventListener('click', (event) => event.stopPropagation())

    dropdown.addEventListener('focusout', (event) => {
        queueMicrotask(() => {
            const nextTarget = event.relatedTarget

            if (
                nextTarget &&
                (dropdown.contains(nextTarget) || container.contains(nextTarget))
            ) {
                return
            }

            const button = container.querySelector('.fi-select-input-btn')

            if (button?.getAttribute('aria-expanded') === 'true') {
                button.click()
            }
        })
    })
}

const cleanUpDetachedDropdowns = () => {
    for (const [dropdown, portal] of portals) {
        const { owner, container } = portal

        if (!owner.isConnected || !container.isConnected) {
            dropdown.remove()
            portals.delete(dropdown)
        }
    }
}

const portalPhoneDropdowns = (root = document) => {
    root.querySelectorAll?.('.fi-phone-country-select .fi-dropdown-panel').forEach((dropdown) => {
        if (portals.has(dropdown)) return

        const owner = dropdown.closest('.fi-phone-country-select')
        const container = owner?.querySelector('.fi-select-input-ctn')

        if (!owner || !container) return

        dropdown.classList.add('fi-phone-country-dropdown')
        closeOnFocusLeavingDropdown(dropdown, container)
        portals.set(dropdown, { owner, container })
        document.body.appendChild(dropdown)
    })
}

const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
            if (node.nodeType !== Node.ELEMENT_NODE) continue

            if (node.matches?.('.fi-phone-country-select .fi-dropdown-panel')) {
                portalPhoneDropdowns(node.parentElement)
            }

            portalPhoneDropdowns(node)
        }
    }

    cleanUpDetachedDropdowns()
})

const startPhoneDropdownPortals = () => {
    portalPhoneDropdowns()
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] })
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startPhoneDropdownPortals, { once: true })
} else {
    startPhoneDropdownPortals()
}
