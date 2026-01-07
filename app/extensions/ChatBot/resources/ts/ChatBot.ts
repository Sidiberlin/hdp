import DeepsetApi, {
	HistoryDocument,
	QueryAnswer,
	QueryAnswerQuery,
	StreamDeltaObject,
	StreamFetchData
} from "./api/DeepsetApi";
import BluespiceApi from "./api/BluespiceApi";
// noinspection ES6UnusedImports
import config from "types-mediawiki/mw/message";
// noinspection ES6UnusedImports
import Title from "types-mediawiki/mw/Title";
import MessageFactory from "./utils/MessageFactory";
import Dom from "./utils/Dom";
import Reference from "./model/Reference";
import MessageReceived from "./model/MessageReceived";
import BrowserStorage from "./utils/BrowserStorage";
import ReferenceFactory from "./utils/ReferenceFactory";
import MessageParser from "./utils/MessageParser";

declare global {
	interface Window {
		bs: { ns: any }
	}
}

export enum FeedbackScore {
	ACCURATE = "ACCURATE",
	INACCURATE = "INACCURATE",
	FAIRLY_ACCURATE = "FAIRLY_ACCURATE",
}

export interface FeedbackEvent {
	resultId: string
	queryId: string
	score: FeedbackScore,
	comment: string,
	tags: string[]
}

export interface ChatHistoryItem {
	query: QueryAnswerQuery
	answer: string
	references: Reference[]
	session_id: string
	time: string
	result_id: string
	query_id: string,
	allDocuments: HistoryDocument[]
}

export default class ChatBot {

	private deepsetApi: DeepsetApi;

	private bluespiceApi: BluespiceApi;

	private dom: Dom;

	private sessionId: string;

	private currentMessage: MessageReceived;

	private messageFactory: MessageFactory;

	private chatHistoryInitializing = false;

	private browserStorage: BrowserStorage;

	public constructor(
		chatUrl: string,
		sessionUrl: string,
		historyUrl: string,
		feedbackUrl: string
	) {
		this.dom = new Dom();

		if ( !chatUrl || !historyUrl || !sessionUrl || !feedbackUrl ) {
			this.throwError( mw.message( 'chat-missing-config' ).text() );
		}

		this.dom.on( Dom.EVENT_OPEN_CHAT, this.restoreSession.bind( this ) );
		this.dom.on( Dom.EVENT_CLOSE_CHAT, this.clearSession.bind( this ) );
		this.dom.on( Dom.EVENT_SEND_MESSAGE, this.sendMessage.bind( this ) );
		this.dom.on( Dom.EVENT_EXPORT_CHAT, this.exportChat.bind( this ) );
		this.dom.on( Dom.EVENT_FOLLOWUP, this.followUp.bind( this ) );
		this.dom.on( Dom.EVENT_MOUSE_WHEEL_USED, this.setAutoScrolling.bind( this ) );

		this.messageFactory = new MessageFactory( this.dom );

		this.bluespiceApi = new BluespiceApi();

		this.deepsetApi = new DeepsetApi( chatUrl, historyUrl, sessionUrl, feedbackUrl );
		this.deepsetApi.on( DeepsetApi.EVENT_ERROR, this.throwError.bind( this ) );
		this.deepsetApi.on( DeepsetApi.STREAM_EVENT_DELTA, this.onStreamEventDelta.bind( this ) );

		this.init();
	}

	public async getChatHistory(): Promise<ChatHistoryItem[]> {
		const value = this.browserStorage.getRawChatHistory();
		if ( !value ) {
			try {
				const chatHistory = await this.deepsetApi.getChatHistory( this.sessionId );
				try {
					this.browserStorage.updateChatHistory( chatHistory );
				} catch ( error ) {
					this.throwError( error );
				}
				return chatHistory;
			} catch ( error ) {
				this.throwError( error.message );
			}
		}

		return value.map( ( item: ChatHistoryItem ) => {
			item.references = item.references.map( ( reference: any ) => ReferenceFactory.createFromJson( reference ) );
			return item;
		} );
	}

	private init(): void {
		this.browserStorage = new BrowserStorage();
		const mode = this.browserStorage.getMode();
		if ( mode ) {
			this.dom.setMode( mode );
		}

		const maximized = this.browserStorage.getMaximizedState();
		if ( maximized ) {
			this.dom.maximizeChat();
		}
	}

	private async restoreSession(): Promise<void> {
		this.dom.disableSendMessages();
		this.dom.disableExport();
		this.dom.showRestoreSessionMessage();

		await this.initSessionId();
		await this.initChatHistory();

		this.dom.hideRestoreSessionMessage();
		this.dom.enableSendMessages();
	}

	private async initSessionId(): Promise<void> {
		// Get or fetch session id
		let sessionId = this.browserStorage.getRunningSession();
		if ( !sessionId ) {
			try {
				sessionId = await this.deepsetApi.fetchSessionId();
				this.browserStorage.setRunningSession( sessionId );
				this.dom.setChatButtonActive( true );
			} catch ( error ) {
				this.throwError( error.message );
			}
		}

		this.sessionId = sessionId;
	}

	private async initChatHistory(): Promise<void> {
		if ( this.chatHistoryInitializing ) {
			return;
		}
		this.chatHistoryInitializing = true;
		const chatHistory = await this.getChatHistory();
		if ( chatHistory.length === 0 ) {
			this.chatHistoryInitializing = false;
			this.dom.showBanner();
			this.messageFactory.createGreetingMessage();
			return;
		}

		chatHistory.forEach( ( item: ChatHistoryItem ) => {
			if ( item.query.type === 'followUp' ) {
				this.messageFactory.createFollowUpMessage( mw.msg( 'chatbot-' + item.query.followUpType ), item.time );
			} else {
				this.messageFactory.createSentMessage( item.query.query, item.time );
			}

			const received = this.messageFactory.createReceivedMessage(
				item.query.query,
				item.answer,
				item.references,
				item.time
			);

			received.appendReferenceList( item.references );

			// If it is the last item in the chat history, append the buttons, follow-up options and scroll to the bottom
			if ( chatHistory.indexOf( item ) === chatHistory.length - 1 ) {
				received.appendMessageButtons( item.allDocuments, item.result_id, item.query_id );
				received.on( MessageReceived.FEEDBACK_EVENT, this.onFeedback.bind( this ) );
				this.dom.showFollowUpOptions( item.query.query );
				Dom.scroll( received.element );
			} else {
				received.appendMessageButtons( item.allDocuments );
			}
		} );

		this.dom.enableExport();
		this.chatHistoryInitializing = false;
	}

	private async sendMessage( message: string, followUpType?: string ): Promise<void> {
		if ( !message ) {
			return;
		}
		this.dom.clearFollowUpOptions();
		if ( followUpType ) {
			this.messageFactory.createFollowUpMessage( mw.msg( 'chatbot-' + followUpType ) );
		} else {
			this.messageFactory.createSentMessage( message );
		}

		this.dom.disableSendMessages();
		this.dom.setAnswerIsBeingProcessedMessage( true );

		const response = await this.deepsetApi.sendMessage( message, this.sessionId, followUpType );
		this.finalizeStreamEvent( response );
		this.appendToSessionHistory( response );
	}

	private onStreamEventDelta( delta: StreamDeltaObject, fetchData: StreamFetchData ): void {
		if ( !this.currentMessage ) {
			this.currentMessage = this.messageFactory.createReceivedMessage( fetchData.query );
			this.currentMessage.startStreaming();
			this.currentMessage.on( MessageReceived.FEEDBACK_EVENT, this.onFeedback.bind( this ) );
			this.currentMessage.on( MessageReceived.STREAM_FINISHED_EVENT, this.onStreamFinished.bind( this ) );
			this.dom.setAnswerIsBeingProcessedMessage( false );
		}

		this.currentMessage.appendText( delta.delta.text );
	}

	private onStreamFinished(): void {
		this.dom.showFollowUpOptions( this.currentMessage.getQuery() );
		this.dom.clearMessageInput();
		this.dom.enableSendMessages();

		this.currentMessage.scrollToBottom();
		this.currentMessage = null;
	}

	private finalizeStreamEvent( response: QueryAnswer ): void {
		if ( !this.currentMessage ) {
			this.throwError( 'No current message' );
		}

		this.currentMessage.stopStreaming( response );
	}

	private async onFeedback( feedback: FeedbackEvent ): Promise<void> {
		await this.deepsetApi.sendFeedback( feedback, this.sessionId );
	}

	private async appendToSessionHistory( data: QueryAnswer ): Promise<void> {
		const chatHistory = await this.getChatHistory();

		chatHistory.push( {
			query: data.query,
			answer: data.answer,
			references: data.references,
			session_id: this.sessionId,
			time: new Date().toISOString(),
			result_id: data.result_id,
			query_id: data.query_id,
			allDocuments: data.allDocuments
		} );
		try {
			this.browserStorage.updateChatHistory( chatHistory );
		} catch ( error ) {
			this.throwError( error );
		}
		this.dom.enableExport();
	}

	private async exportChat( format: string ): Promise<void> {
		const chatHistory = this.browserStorage.getRawChatHistory();

		if ( !chatHistory || chatHistory.length === 0 ) {
			return this.throwError( mw.message( 'chat-export-error' ).text() );
		}

		const dateString = new Date().toLocaleDateString() + "_" + new Date().toLocaleTimeString( [], { timeStyle: 'short' } );

		try {
			chatHistory.forEach( ( item: ChatHistoryItem ) => {
				item.answer = MessageParser.parseMarkup( item.answer );
			} );
			this.bluespiceApi.downloadChatHistory( chatHistory, `ExportHdP-Chatbot_${ dateString }`, format );
		} catch ( error ) {
			this.throwError( error.message );
		}
	}

	private setAutoScrolling( event: WheelEvent ): void {
		if ( !this.currentMessage ) {
			return;
		}

		const scrolledDown = event.deltaY > 0;
		if ( scrolledDown ) {
			const element = event.currentTarget as HTMLElement;
			const buffer = 600;
			const atBottom = element.scrollHeight - element.scrollTop <= element.clientHeight + buffer;

			if ( atBottom ) {
				this.currentMessage.allowScrolling();

				return;
			}
		}

		this.currentMessage.preventScrolling();
	}

	private clearSession(): void {
		this.browserStorage.clearAll();
		this.dom.setChatButtonActive( false );
	}

	private throwError( message: string ): void {
		this.dom.displayErrorMessage( message );
		throw new Error( message );
	}

	private followUp( type, originalQuery ): void {
		this.sendMessage( originalQuery, type );
	}
}

function onReady() {
	new ChatBot(
		mw.config.get( 'bmbfApiChatUrl' ) as string,
		mw.config.get( 'bmbfApiSessionUrl' ) as string,
		mw.config.get( 'bmbfApiHistoryUrl' ) as string,
		mw.config.get( 'bmbfDeepsetApiFeedbackUrl' ) as string
	);
}

if ( document.readyState !== "loading" ) {
	onReady();
} else {
	document.addEventListener( "DOMContentLoaded", onReady );
}
