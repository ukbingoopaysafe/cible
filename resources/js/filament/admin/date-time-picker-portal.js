const pickerStates = new Map()
const configuredRoots = new WeakSet()
const pendingRoots = new WeakSet()

const viewportPadding = 8
const pickerGap = 8
const openClass = 'fi-cible-date-time-picker-panel-open'
const compactClass = 'fi-cible-date-time-picker-panel-compact'

const getPickerData = (root) =>
    root._x_dataStack?.find(
        (candidate) =>
            typeof candidate?.togglePanelVisibility === 'function' &&
            typeof candidate?.closePanel === 'function' &&
            typeof candidate?.isPanelOpen === 'boolean',
    )

const hasPopoverApi = (panel) =>
    typeof panel.showPopover === 'function' &&
    typeof panel.hidePopover === 'function'

const getViewportBounds = () => {
    const visualViewport = window.visualViewport

    if (visualViewport && visualViewport.width > 0 && visualViewport.height > 0) {
        return {
            left: visualViewport.offsetLeft,
            top: visualViewport.offsetTop,
            right: visualViewport.offsetLeft + visualViewport.width,
            bottom: visualViewport.offsetTop + visualViewport.height,
            height: visualViewport.height,
        }
    }

    const width = document.documentElement.clientWidth || window.innerWidth
    const height = document.documentElement.clientHeight || window.innerHeight

    return { left: 0, top: 0, right: width, bottom: height, height }
}

const positionPanel = (state) => {
    const { data, panel, root, trigger } = state

    if (!state.nativeOpen || !data.isPanelOpen || !panel.isConnected || !trigger.isConnected) {
        return
    }

    const viewport = getViewportBounds()
    const availableWidth = Math.max(0, viewport.right - viewport.left - viewportPadding * 2)

    panel.style.setProperty('--cible-picker-available-width', `${availableWidth}px`)
    panel.classList.toggle(compactClass, viewport.height < 420)

    const triggerRect = trigger.getBoundingClientRect()
    const panelRect = panel.getBoundingClientRect()

    if (panelRect.width === 0 || panelRect.height === 0) {
        requestPanelPosition(state)

        return
    }

    const viewportLeft = viewport.left + viewportPadding
    const viewportTop = viewport.top + viewportPadding
    const viewportRight = viewport.right - viewportPadding
    const viewportBottom = viewport.bottom - viewportPadding
    const availableBelow = Math.max(
        0,
        viewportBottom - triggerRect.bottom - pickerGap,
    )
    const availableAbove = Math.max(
        0,
        triggerRect.top - pickerGap - viewportTop,
    )
    const opensBelow =
        panelRect.height <= availableBelow ||
        (panelRect.height > availableAbove && availableBelow >= availableAbove)
    const direction = window.getComputedStyle(root).direction
    const preferredLeft =
        direction === 'rtl'
            ? triggerRect.right - panelRect.width
            : triggerRect.left
    const maxLeft = Math.max(viewportLeft, viewportRight - panelRect.width)
    const left = Math.min(Math.max(viewportLeft, preferredLeft), maxLeft)
    const preferredTop = opensBelow
        ? triggerRect.bottom + pickerGap
        : triggerRect.top - pickerGap - panelRect.height
    const maxTop = Math.max(viewportTop, viewportBottom - panelRect.height)
    const top = Math.min(Math.max(viewportTop, preferredTop), maxTop)

    panel.style.setProperty('left', `${Math.round(left)}px`, 'important')
    panel.style.setProperty('top', `${Math.round(top)}px`, 'important')
}

const requestPanelPosition = (state) => {
    if (state.positionFrame !== null) {
        return
    }

    state.positionFrame = requestAnimationFrame(() => {
        state.positionFrame = null
        positionPanel(state)
    })
}

const stopPositioning = (state) => {
    if (state.positionFrame !== null) {
        cancelAnimationFrame(state.positionFrame)
        state.positionFrame = null
    }

    document.removeEventListener('scroll', state.schedulePosition, true)
    window.removeEventListener('resize', state.schedulePosition)
    window.visualViewport?.removeEventListener('resize', state.schedulePosition)
    window.visualViewport?.removeEventListener('scroll', state.schedulePosition)
    state.resizeObserver?.disconnect()
    state.resizeObserver = null
    state.positioningStarted = false
}

const startPositioning = (state) => {
    if (state.positioningStarted) {
        return
    }

    state.positioningStarted = true
    document.addEventListener('scroll', state.schedulePosition, {
        capture: true,
        passive: true,
    })
    window.addEventListener('resize', state.schedulePosition, { passive: true })
    window.visualViewport?.addEventListener('resize', state.schedulePosition, {
        passive: true,
    })
    window.visualViewport?.addEventListener('scroll', state.schedulePosition, {
        passive: true,
    })

    if (typeof ResizeObserver !== 'undefined') {
        state.resizeObserver = new ResizeObserver(state.schedulePosition)
        state.resizeObserver.observe(state.trigger)
        state.resizeObserver.observe(state.panel)
    }
}

const closePopover = (state) => {
    stopPositioning(state)

    if (state.nativeOpen) {
        try {
            state.panel.hidePopover()
        } catch {
            // A destroyed or already hidden panel needs no further action.
        }
    }

    state.nativeOpen = false
    state.panel.classList.remove(openClass, compactClass)
    state.panel.style.removeProperty('--cible-picker-available-width')
    state.panel.style.removeProperty('left')
    state.panel.style.removeProperty('top')
}

const disablePopoverForState = (state) => {
    closePopover(state)
    state.usePopover = false
    state.panel.removeAttribute('popover')
}

const cancelFilamentPositioning = (data) => {
    data.positionGeneration++
    data.positioningCleanup?.()
    data.positioningCleanup = null
}

const openPopover = (state, data) => {
    if (!state.usePopover || !data.isPanelOpen || !state.root.isConnected) {
        return
    }

    data.$nextTick(() =>
        requestAnimationFrame(() => {
            if (!state.usePopover || !data.isPanelOpen || !state.panel.isConnected) {
                closePopover(state)

                return
            }

            state.panel.classList.add(openClass)

            try {
                if (!state.nativeOpen) {
                    state.panel.showPopover()
                }
            } catch {
                disablePopoverForState(state)

                return
            }

            state.nativeOpen = true
            cancelFilamentPositioning(data)
            startPositioning(state)
            positionPanel(state)
        }),
    )
}

const patchPicker = (root, panel, data) => {
    if (configuredRoots.has(root) || !hasPopoverApi(panel)) {
        return
    }

    const trigger = root.querySelector('[x-ref="button"]')

    if (!trigger || !panel.querySelector('[x-ref="calendar"]')) {
        return
    }

    configuredRoots.add(root)
    panel.setAttribute('popover', 'manual')

    const state = {
        data,
        panel,
        root,
        trigger,
        nativeOpen: false,
        usePopover: true,
        positionFrame: null,
        positioningStarted: false,
        resizeObserver: null,
        schedulePosition: null,
        originalClosePanel: data.closePanel,
        originalTogglePanelVisibility: data.togglePanelVisibility,
    }
    state.schedulePosition = () => requestPanelPosition(state)
    pickerStates.set(panel, state)

    data.closePanel = function (...args) {
        closePopover(state)

        return state.originalClosePanel.apply(this, args)
    }

    data.togglePanelVisibility = function (...args) {
        const result = state.originalTogglePanelVisibility.apply(this, args)

        if (this.isPanelOpen) {
            openPopover(state, this)
        } else {
            closePopover(state)
        }

        return result
    }
}

const cleanUpDetachedPickers = () => {
    for (const [panel, state] of pickerStates) {
        if (state.root.isConnected) {
            continue
        }

        closePopover(state)

        if (state.data.closePanel !== state.originalClosePanel) {
            state.data.closePanel = state.originalClosePanel
        }

        if (state.data.togglePanelVisibility !== state.originalTogglePanelVisibility) {
            state.data.togglePanelVisibility = state.originalTogglePanelVisibility
        }

        pickerStates.delete(panel)
    }
}

const queuePicker = (root, attempts = 0) => {
    if (configuredRoots.has(root) || pendingRoots.has(root)) {
        return
    }

    pendingRoots.add(root)

    const configure = () => {
        if (!root.isConnected) {
            pendingRoots.delete(root)

            return
        }

        const panel = root.querySelector('[data-cible-date-time-picker-panel]')
        const data = getPickerData(root)

        if (panel && data && panel.querySelector('[x-ref="calendar"]')) {
            pendingRoots.delete(root)
            patchPicker(root, panel, data)

            return
        }

        if (attempts >= 120) {
            pendingRoots.delete(root)

            return
        }

        attempts += 1
        requestAnimationFrame(configure)
    }

    configure()
}

const findPickerRoots = (node) => {
    const candidates = []

    if (node.nodeType === Node.ELEMENT_NODE && node.matches('[x-data]')) {
        candidates.push(node)
    }

    node.querySelectorAll?.('[x-data]').forEach((candidate) =>
        candidates.push(candidate),
    )

    candidates
        .filter((candidate) =>
            candidate
                .getAttribute('x-data')
                ?.includes('dateTimePickerFormComponent'),
        )
        .forEach((candidate) => queuePicker(candidate))
}

const closeAllOpenPickers = () => {
    for (const state of pickerStates.values()) {
        if (state.data.isPanelOpen) {
            state.data.closePanel()
        }
    }
}

const startDateTimePickerPopovers = () => {
    if (!document.body) {
        return
    }

    findPickerRoots(document)

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    findPickerRoots(node)
                }
            }
        }

        cleanUpDetachedPickers()
    })

    observer.observe(document.body, { childList: true, subtree: true })

    document.addEventListener('modal-closed', closeAllOpenPickers)
    document.addEventListener('livewire:navigating', closeAllOpenPickers)
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startDateTimePickerPopovers, {
        once: true,
    })
} else {
    startDateTimePickerPopovers()
}
