import EventEmitter from "events";
import ExportPopup from "../model/ExportPopup";
import ClosePopup from "../model/ClosePopup";
import Message from "../model/Message";
import BrowserStorage from "./BrowserStorage";
import Accessibility from "./Accessibility";

declare const mw: any;
declare const blueSpiceDiscovery: any;

export enum ChatSizeMode {
	Small = "small",
	Medium = "medium",
	Large = "large"
}

export default class Dom extends EventEmitter {
	public static readonly EVENT_OPEN_CHAT: string = "openChat";

	public static readonly EVENT_CLOSE_CHAT: string = "closeChat";

	public static readonly EVENT_MINIMIZE_CHAT: string = "minimizeChat";

	public static readonly EVENT_SEND_MESSAGE: string = "sendMessage";

	public static readonly EVENT_EXPORT_CHAT: string = "exportChat";

	public static readonly EVENT_MOUSE_WHEEL_USED: string = "wheelUsed";

	public static readonly EVENT_FOLLOWUP: string = "followup";

	public static readonly FOLLOW_UP_TYPES: string[] = [
		'followup_short', 'followup_elaborate', 'followup_bulletpoints', 'followup_onlytext', 'followup_citations'
	];

	private chat: HTMLElement;

	private chatBody: HTMLElement;

	private followUpPanel: HTMLElement;

	private restoreSessionMessage: HTMLElement;

	private chatContainer: HTMLElement;

	private statusMessageContainer: HTMLElement;

	private errorMessageContainer: HTMLElement;

	private dismissErrorButton: HTMLElement;

	private sendButton: HTMLButtonElement;

	private maximizeButton: HTMLButtonElement;

	private exportButton: HTMLButtonElement;

	private minimizeButton: HTMLButtonElement;

	private resizeButton: HTMLButtonElement;

	private closeButton: HTMLButtonElement;

	private mediumSizeContentWrapper: HTMLElement;

	private messageInput: HTMLInputElement;

	private answerProcessingMessage: HTMLElement;

	private exportOptions: ExportPopup;

	private chatBanner: HTMLElement;

	private bannerClose: HTMLButtonElement;

	private bannerHelp: HTMLButtonElement;

	private closeDialog: ClosePopup;

	private mode: ChatSizeMode;

	private isExportDisabled: boolean;

	private followUpOriginalQuery: string;

	private browserStorage: BrowserStorage;

	private accessibility: Accessibility;

	public constructor() {
		super();
		this.browserStorage = new BrowserStorage();
		this.initDomElements();
		this.accessibility = new Accessibility( this.statusMessageContainer, this.chatContainer )
		this.mode = ChatSizeMode.Small;
		this.isExportDisabled = false;
	}

	public appendMessage( message: Message ): void {
		this.chatBody.appendChild( message.element );
	}

	public disableSendMessages(): void {
		this.sendButton.setAttribute( 'disabled', 'disabled' );
		this.messageInput.setAttribute( 'disabled', 'disabled' );
	}

	public enableSendMessages(): void {
		this.sendButton.removeAttribute( 'disabled' );
		this.messageInput.removeAttribute( 'disabled' );

		// Only focus if banner is not there for accessibility reasons
		if ( this.chatBanner.classList.contains( 'invisible' ) ) {
			this.messageInput.focus();
		} else {
			this.bannerClose.focus();
		}

		this.messageInput.style.height = 'auto';
		this.messageInput.style.height = Math.min( this.messageInput.scrollHeight, 140 ) + 'px';
	}

	public displayErrorMessage( message: string ): void {
		const errorMessage = document.createElement( 'div' );
		errorMessage.classList.add( 'error' );
		errorMessage.innerText = message;
		this.errorMessageContainer.appendChild( errorMessage );
		this.errorMessageContainer.style.display = 'block';
		this.hideRestoreSessionMessage();
	}

	public disableExport(): void {
		this.isExportDisabled = true;
		this.exportButton.setAttribute( 'disabled', 'disabled' );

		if ( this.exportOptions ) {
			this.exportOptions.hide();
		}

		if ( this.closeDialog ) {
			this.closeDialog.disableExportButton();
		}
	}

	public enableExport(): void {
		this.isExportDisabled = false;
		this.exportButton.removeAttribute( 'disabled' );

		if ( this.closeDialog ) {
			this.closeDialog.enableExportButton();
		}
	}

	public hideBanner(): void {
		this.chatBanner.classList.add( 'invisible' );
	}

	public showBanner(): void {
		this.chatBanner.classList.remove( 'invisible' );
	}

	public clearMessageInput(): void {
		this.messageInput.value = '';
	}

	public showRestoreSessionMessage(): void {
		this.restoreSessionMessage.classList.remove( 'hidden' );
		this.accessibility.setStatus( mw.message( 'chat-restore-session-text' ).text() );
	}

	public hideRestoreSessionMessage(): void {
		this.restoreSessionMessage.classList.add( 'hidden' );
	}

	public clearChat(): void {
		this.chatBody.innerHTML = '';
		this.messageInput.value = '';
		this.disableExport();
	}

	public getChatBody(): HTMLElement {
		return this.chatBody;
	}

	public setAnswerIsBeingProcessedMessage( show: boolean ) {
		if ( show ) {
			this.answerProcessingMessage = document.createElement( 'div' );
			this.answerProcessingMessage.classList.add( 'answer-processing' );
			this.answerProcessingMessage.innerText = mw.message( 'chat-answer-processing' ).text();
			this.chatBody.appendChild( this.answerProcessingMessage );
			Dom.scroll( this.answerProcessingMessage, "end" );
			this.accessibility.setStatus( mw.message( 'chat-answer-processing' ).text() );

			return
		}

		this.answerProcessingMessage.remove();
	}

	public maximizeChat(): void {
		this.chatContainer.classList.remove( 'hidden' );
		this.maximizeButton.classList.add( 'hidden' );
		this.emit( Dom.EVENT_OPEN_CHAT );
		// TODO: Remove if possible to attach to event fired above
		const event = new Event( Dom.EVENT_OPEN_CHAT );
		window.dispatchEvent( event );
		this.browserStorage.setMaximized();
		this.accessibility.enableFocusTrap( this.resizeButton, this.sendButton );
	}

	public minimizeChat(): void {
		this.chatContainer.classList.add( 'hidden' );
		this.maximizeButton.classList.remove( 'hidden' );
		this.clearChat();
		this.setMode( ChatSizeMode.Small );
		this.browserStorage.unsetMaximized();

		this.emit( Dom.EVENT_MINIMIZE_CHAT );
		// TODO: Remove is possible to attach to event fired above
		const event = new Event( Dom.EVENT_MINIMIZE_CHAT );
		window.dispatchEvent( event );
		this.accessibility.disableFocusTrap();
	}

	public setChatButtonActive( isActive: boolean ): void {
		if ( isActive ) {
			this.maximizeButton.classList.add( 'active' );
		} else {
			this.maximizeButton.classList.remove( 'active' );
		}
	}

	public setMode( mode: ChatSizeMode ): void {
		if ( this.mode === mode ) {
			return;
		}

		if ( mode === ChatSizeMode.Medium && window.hasOwnProperty( 'blueSpiceDiscovery' ) ) {
			blueSpiceDiscovery.ui.hideSidebarPrimary();
			blueSpiceDiscovery.ui.hideSidebarSecondary();
		}

		this.browserStorage.setMode( mode );
		if ( this.mode === ChatSizeMode.Medium ) {
			this.toggleContentAndChatSideBySide( false );
		}

		if ( mode === ChatSizeMode.Medium ) {
			this.chat.classList.add( 'medium' );
			this.chat.classList.remove( 'large' );
			this.mode = ChatSizeMode.Medium;
			this.toggleContentAndChatSideBySide( true );
			document.body.style.overflow = 'auto';
			this.accessibility.setStatus( mw.message( 'chat-status-mode-side-by-side' ).text() );

			return;
		}

		if ( mode === ChatSizeMode.Small ) {
			this.chat.classList.remove( 'medium' );
			this.chat.classList.remove( 'large' );
			document.body.style.overflow = 'auto';
			this.mode = ChatSizeMode.Small;
			this.accessibility.setStatus( mw.message( 'chat-status-mode-normal' ).text() );

			return;
		}

		this.chat.classList.remove( 'medium' );
		this.chat.classList.add( 'large' );
		document.body.style.overflow = 'hidden';
		this.mode = ChatSizeMode.Large;
		this.accessibility.setStatus( mw.message( 'chat-status-mode-full-screen' ).text() );
	}

	public showFollowUpOptions( originalQuery: string ): void {
		this.followUpOriginalQuery = originalQuery;
		this.followUpPanel.classList.remove( 'hidden' );
	}

	public clearFollowUpOptions(): void {
		this.followUpOriginalQuery = null;
		this.followUpPanel.classList.add( 'hidden' );
	}

	private initFollowUpPanel(): void {
		this.followUpPanel = document.getElementById( 'followUpPanel' ) as HTMLElement;
		this.followUpOriginalQuery = null;

		Dom.FOLLOW_UP_TYPES.forEach( ( type ) => {
			const button = document.createElement( 'button' );
			button.classList.add( 'followup-option' );
			button.innerText = mw.msg( 'chatbot-' + type );
			button.addEventListener( 'click', () => {
				if ( !this.followUpOriginalQuery ) {
					return;
				}
				this.emit( Dom.EVENT_FOLLOWUP, type, this.followUpOriginalQuery );
				this.followUpOriginalQuery = null;
			} );
			this.followUpPanel.appendChild( button );
		} );
	}

	public static scroll( element: HTMLElement, block: ScrollLogicalPosition = "start" ): void {
		element.scrollIntoView( { behavior: 'instant', block: block } );
	}

	private onSendMessage(): void {
		this.emit( Dom.EVENT_SEND_MESSAGE, this.messageInput.value );
	}

	private onExportChat(): void {
		if ( !this.exportOptions && !this.isExportDisabled ) {
			this.exportOptions = new ExportPopup( this.exportButton.parentElement, this.exportButton );
			this.exportOptions.on( ExportPopup.EXPORT, this.onExport.bind( this ) );
		}
		this.exportOptions.toggle();
	}

	private onExport( format: string ): void {
		this.emit( Dom.EVENT_EXPORT_CHAT, format );
	}

	private resizeChat(): void {
		if ( this.mode === ChatSizeMode.Small ) {
			this.setMode( ChatSizeMode.Medium );
		} else if ( this.mode === ChatSizeMode.Medium ) {
			this.setMode( ChatSizeMode.Large );
		} else {
			this.setMode( ChatSizeMode.Small );
		}
	}

	private toggleContentAndChatSideBySide( active: boolean ): void {
		if ( active ) {
			document.body.classList.add( 'chat-medium-content-wrapper' );
		} else {
			document.body.classList.remove( 'chat-medium-content-wrapper' );
		}
	}

	private onCloseChat(): void {
		if ( !this.closeDialog ) {
			this.closeDialog = new ClosePopup( this.closeButton.parentElement, this.closeButton, this.isExportDisabled );
			this.closeDialog.on( ClosePopup.EXPORT, this.onExportAndCloseChat.bind( this ) );
			this.closeDialog.on( ClosePopup.CLOSE, this.closeChat.bind( this ) );
		}
		this.closeDialog.toggle();
	}

	private onExportAndCloseChat(): void {
		if ( !this.exportOptions && !this.isExportDisabled ) {
			this.exportOptions = new ExportPopup( this.exportButton.parentElement, this.exportButton );
			this.exportOptions.on( ExportPopup.EXPORT, this.onExportClose.bind( this ) );
		}
		this.exportOptions.show();
	}

	private onExportClose( format: string ): void {
		this.emit( Dom.EVENT_EXPORT_CHAT, format );
		this.closeChat();
	}

	private closeChat(): void {
		this.minimizeChat();
		this.emit( Dom.EVENT_CLOSE_CHAT );
	}

	private onDismissError(): void {
		this.errorMessageContainer.style.display = 'none';
	}

	private initDomElements(): void {
		this.maximizeButton = document.getElementById( 'maximizeChat' ) as HTMLButtonElement;
		this.maximizeButton.addEventListener( 'click', this.maximizeChat.bind( this ) );
		if ( this.browserStorage.getRunningSession() ) {
			this.setChatButtonActive( true );
		}

		this.minimizeButton = document.getElementById( 'minimizeChat' ) as HTMLButtonElement;
		this.minimizeButton.addEventListener( 'click', this.minimizeChat.bind( this ) );

		this.resizeButton = document.getElementById( 'resizeChat' ) as HTMLButtonElement;
		this.resizeButton.addEventListener( 'click', this.resizeChat.bind( this ) );

		this.closeButton = document.getElementById( 'closeChat' ) as HTMLButtonElement;
		this.closeButton.addEventListener( 'click', this.onCloseChat.bind( this ) );

		this.sendButton = document.getElementById( 'sendMessage' ) as HTMLButtonElement;
		this.sendButton.addEventListener( 'click', this.onSendMessage.bind( this ) );

		this.exportButton = document.getElementById( 'exportChat' ) as HTMLButtonElement;
		this.exportButton.addEventListener( 'click', this.onExportChat.bind( this ) );

		this.dismissErrorButton = document.getElementById( 'dismissError' ) as HTMLElement;
		this.dismissErrorButton.addEventListener( 'click', this.onDismissError.bind( this ) );

		this.messageInput = document.getElementById( 'messageInput' ) as HTMLInputElement;
		this.messageInput.addEventListener( "keydown", ( event ) => {
			if ( event.key === "Enter" ) {
				event.preventDefault();
				this.onSendMessage();
			}
			// Set the height to the scrollHeight up to a maximum value
			this.messageInput.style.height = Math.min( this.messageInput.scrollHeight, 140 ) + 'px';
		} );

		this.messageInput.addEventListener( "input", () => {
			if ( this.messageInput.scrollHeight < 80 ) {
				return;
			}
			this.messageInput.style.height = 'auto';
			// Set the height to the scrollHeight up to a maximum value
			this.messageInput.style.height = Math.min( this.messageInput.scrollHeight, 140 ) + 'px';
		} );
		// Make input not resizable.  By default 3 rows, max 6 rows, then scroll
		this.messageInput.style.resize = 'none';
		this.messageInput.setAttribute( 'rows', '3' );

		this.chat = document.getElementById( 'chat' ) as HTMLElement;

		this.chatContainer = document.getElementById( 'chatContainer' ) as HTMLElement;
		this.chatContainer.classList.add( 'hidden' );

		this.chatBody = document.getElementById( 'chatBody' ) as HTMLElement;
		this.chatBody.addEventListener( "wheel", ( event ) => {
			this.emit( Dom.EVENT_MOUSE_WHEEL_USED, event );
		} );

		this.errorMessageContainer = document.getElementById( 'errorMessageContainer' ) as HTMLElement;

		// Use bluespice wcag status container
		this.statusMessageContainer = document.getElementById( 'mws-wcag-generic-status-container' ) as HTMLElement;
		if ( !this.statusMessageContainer ) {
			throw new Error( 'Status message container not found' );
		}

		this.restoreSessionMessage = document.getElementById( 'restoreSessionMessage' ) as HTMLElement;
		this.restoreSessionMessage.classList.add( 'hidden' );

		this.mediumSizeContentWrapper = document.createElement( 'div' ) as unknown as HTMLElement;
		this.mediumSizeContentWrapper.classList.add( 'chat-medium-content-wrapper' );

		this.chatBanner = document.getElementById( 'chat-banner' ) as HTMLElement;

		this.bannerClose = document.getElementById( 'chat-banner-close' ) as HTMLButtonElement;
		this.bannerClose.addEventListener( 'click', this.hideBanner.bind( this ) );

		this.bannerHelp = document.getElementById( 'chat-banner-help' ) as HTMLButtonElement;
		this.bannerHelp.addEventListener( 'click', () => {
			window.location.href = mw.util.getUrl( 'Chatbot-FAQ' );
		} );

		this.initFollowUpPanel();
	}
}
