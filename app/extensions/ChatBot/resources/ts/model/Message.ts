import EventEmitter from "events";
import MessageParser from "../utils/MessageParser";
import Dom from "../utils/Dom";

export default class Message extends EventEmitter {
	public element: HTMLElement;

	protected message: string;

	protected messageText: HTMLParagraphElement;

	protected messageTextContainer: HTMLDivElement;

	protected messageContainer: HTMLDivElement;

	private preventScroll: boolean;

	public constructor( message: string | null, timestamp: string ) {
		super();

		this.message = message || '';
		this.element = document.createElement( 'div' );
		this.element.classList.add( 'message' );

		this.messageContainer = document.createElement( 'div' );
		this.messageContainer.classList.add( 'message-container' );
		this.messageTextContainer = document.createElement( 'div' );
		this.messageTextContainer.classList.add( 'message-text' );

		this.messageText = document.createElement( 'p' );
		if ( message ) {
			this.messageText.innerHTML = MessageParser.parseMarkup( message );
		}
		this.messageTextContainer.appendChild( this.messageText );
		this.messageContainer.appendChild( this.messageTextContainer );

		const timestampElement = document.createElement( 'span' );
		timestampElement.classList.add( 'timestamp' );
		timestampElement.innerText = new Date( timestamp ).toLocaleTimeString( [], { timeStyle: 'short' } );
		this.messageContainer.appendChild( timestampElement );

		this.element.appendChild( this.messageContainer );

		this.allowScrolling();
	}

	public addClass( className: string ): void {
		this.element.classList.add( className );
	}

	public addAttribute( attr: string ) {
		this.element.setAttribute( `data-${ attr }`, '' );
	}

	public removeAttribute( attr: string ) {
		this.element.removeAttribute( `data-${ attr }` );
	}

	public addAriaLabel( label: string ) {
		this.element.setAttribute( 'aria-label', label )
	}

	public scrollToBottom(): void {
		if ( this.preventScroll ) {
			return;
		}

		Dom.scroll( this.element, "end" );
	}

	public scrollToStart(): void {
		if ( this.preventScroll ) {
			return;
		}

		Dom.scroll( this.element );
	}

	public preventScrolling(): void {
		this.preventScroll = true
	}

	public allowScrolling(): void {
		this.preventScroll = false
	}
}
