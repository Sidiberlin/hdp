import EventEmitter from "events";

export default class Popup extends EventEmitter {

	protected dialog: HTMLElement;

	protected closeButton: HTMLButtonElement;

	protected body: HTMLElement;

	protected expandButton: HTMLButtonElement;

	public constructor( header: string, appendTo: HTMLElement, expandButton: HTMLButtonElement ) {
		super();

		this.dialog = document.createElement( 'div' );
		this.dialog.classList.add( 'popup-chatbot-cnt' );

		this.dialog.appendChild( this.buildHeader( header ) );
		this.dialog.appendChild( this.buildContent() );
		this.dialog.hidden = true;

		this.expandButton = expandButton;

		appendTo.append( this.dialog );
	}

	protected buildHeader( header: string ): HTMLElement {
		const headerCnt = document.createElement( 'div' );
		headerCnt.classList.add( 'popup-header-cnt' );

		const headerText = document.createElement( 'h6' );
		headerText.textContent = header;
		headerCnt.appendChild( headerText );

		this.closeButton = document.createElement( 'button' );
		this.closeButton.classList.add( 'popup-close-btn' );
		this.closeButton.textContent = 'x';
		this.closeButton.addEventListener( 'click', () => {
			this.hide();
			this.emit( 'cancel' );
		} );
		headerCnt.appendChild( this.closeButton );
		return headerCnt;
	}

	protected buildContent(): HTMLElement {
		const contentCnt = document.createElement( 'div' );
		contentCnt.classList.add( 'popup-body-cnt' );
		contentCnt.appendChild( this.getBody() );

		return contentCnt;
	}

	public setBody( body: HTMLElement ) {
		this.body = body;
	}

	public getBody(): HTMLElement {
		return this.body;
	}

	public show(): void {
		this.dialog.hidden = false;
		this.expandButton.setAttribute( 'aria-expanded', "true");
	}

	public hide(): void {
		this.dialog.hidden = true;
		this.expandButton.setAttribute( 'aria-expanded', "false");
	}

	public isOpen(): boolean {
		return !this.dialog.hidden;
	}

	public toggle(): void {
		if ( this.dialog.hidden ) {
			this.show();
		} else {
			this.hide();
		}
	}

}
