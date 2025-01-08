export default class Accessibility {
	private statusMessageContainer: HTMLElement;

	private chatContainer: HTMLElement;

	public constructor( statusMessageContainer: HTMLElement, chatContainer: HTMLElement ) {
		this.statusMessageContainer = statusMessageContainer;
		this.chatContainer = chatContainer;
	}

	/**
	 * Used only for screenreaders
	 */
	public setStatus( message: string ): void {
		this.statusMessageContainer.textContent = message;
	}

	public enableFocusTrap( firstFocusableElement: HTMLElement, lastFocusableElement: HTMLElement ): void {
		lastFocusableElement.focus();
		this.chatContainer.addEventListener( 'keydown', ( e ) => {
			if ( e.key === "Tab" ) {
				// Shift + Tab to go backwards
				if ( e.shiftKey ) {
					if ( document.activeElement === firstFocusableElement ) {
						e.preventDefault();
						lastFocusableElement.focus();
					}
				} else { // Tab to go forwards
					if ( document.activeElement === lastFocusableElement ) {
						e.preventDefault();
						firstFocusableElement.focus();
					}
				}
			}
		} );
	}

	public disableFocusTrap(): void {
		this.chatContainer.removeEventListener( 'keydown', () => {
		} );
	}
}
