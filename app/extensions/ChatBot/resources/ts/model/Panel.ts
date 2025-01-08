import EventEmitter from "events";

export default class Panel extends EventEmitter {

	protected header: string;

	protected appendTo: HTMLElement;

	protected element: HTMLElement;

	protected closeButton: HTMLButtonElement;

	protected body: HTMLElement;

	public constructor( header: string, appendTo: HTMLElement, shouldInit: boolean = true ) {
		super();
		this.header = header;
		this.appendTo = appendTo;
		if ( shouldInit ) {
			this.init();
		}
	}

	public init(): void {
		this.element = document.createElement( 'div' );
		this.element.classList.add( 'chatbot-aux-panel-cnt' );

		this.element.appendChild( this.buildHeader( this.header ) );
		this.element.appendChild( this.buildContent() );
		this.element.hidden = true;

		this.appendTo.append( this.element );
	}

	protected buildHeader( header: string ): HTMLElement {
		const headerCnt = document.createElement( 'div' );
		headerCnt.classList.add( 'panel-header-cnt' );

		const headerText = document.createElement( 'h6' );
		headerText.textContent = header;
		headerCnt.appendChild( headerText );

		this.closeButton = document.createElement( 'button' );
		this.closeButton.classList.add( 'panel-close-btn' );
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
		contentCnt.classList.add( 'panel-body-cnt' );
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
		this.element.hidden = false;
		this.closeButton.focus();
	}

	public hide(): void {
		this.emit( 'cancel' );
		this.element.hidden = true;
	}

	public isOpen(): boolean {
		return !this.element.hidden;
	}

	public toggle(): void {
		if ( this.element.hidden ) {
			this.show();
		} else {
			this.hide();
		}
	}

}
