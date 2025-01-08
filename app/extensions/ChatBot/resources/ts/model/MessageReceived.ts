import { ChatHistoryItem, FeedbackEvent, FeedbackScore } from "../ChatBot";
import Message from "./Message";
import Reference from "./Reference";
import FeedbackPanel, { Feedback } from "./FeedbackPanel";
import BluespiceApi from "../api/BluespiceApi";
import Dom from "./../utils/Dom";
import RoleLookup from "../utils/RoleLookup";
import { HistoryDocument, QueryAnswer } from "../api/DeepsetApi";
import AllDocumentsPanel from "./AllDocumentsPanel";
import BrowserStorage from "../utils/BrowserStorage";
import MessageParser from "../utils/MessageParser";

declare const mw: any;

export default class MessageReceived extends Message {
	private static readonly STREAM_DELAY: number = 30;

	private static readonly STREAMING_ATTR = 'streaming';

	public static readonly FEEDBACK_EVENT: string = "feedback";

	public static readonly STREAM_FINISHED_EVENT: string = "streamFinished";

	private streaming: boolean = false;

	private charBuffer: string[] = [];

	private feedbackPanel: FeedbackPanel;

	private allDocumentsPanel: AllDocumentsPanel;

	private bluespiceApi: BluespiceApi;

	private query: string;

	public constructor( query: string, message: string | null, timestamp: string ) {
		super( message, timestamp );

		this.query = query;
		this.messageText.setAttribute( 'aria-live', 'assertive' );
		this.bluespiceApi = new BluespiceApi();
	}

	public getQuery(): string {
		return this.query;
	}

	public appendText( text: string ) {
		this.charBuffer.push( text );
	}

	public startStreaming(): void {
		this.messageText.setAttribute( 'aria-busy', 'true' );

		this.streaming = true;
		this.addAttribute( MessageReceived.STREAMING_ATTR );
		this.streamText();
	}

	public stopStreaming( response: QueryAnswer ): void {
		this.streaming = false;

		this.setStreamResultText( response.answer );
		this.appendMessageButtons( response.allDocuments, response.result_id, response.query_id );
		this.appendReferenceList( response.references );
		this.scrollToBottom();
	}

	private async streamText(): Promise<void> {
		let char = '';
		while ( this.streaming || this.charBuffer.length > 0 ) {
			char = this.charBuffer.shift();
			if ( char ) {
				this.messageText.innerHTML += char;
				this.scrollToBottom();
			}
			await new Promise( ( resolve ) => setTimeout( resolve, MessageReceived.STREAM_DELAY ) );
		}

		this.finishStreaming();
	}

	private finishStreaming(): void {
		this.removeAttribute( MessageReceived.STREAMING_ATTR );
		this.applyStreamResultText();
		this.allowScrolling();

		this.emit( MessageReceived.STREAM_FINISHED_EVENT );
	}

	private applyStreamResultText(): void {
		this.messageText.setAttribute( 'aria-busy', 'false' );
		this.messageText.innerHTML = MessageParser.parseMarkup( this.message );
	}

	public setStreamResultText( text: string ): void {
		this.message = text;
	}

	public appendReferenceList( references: Reference[] ): void {
		if ( references.length === 0 ) {
			return;
		}

		const container = document.createElement( 'div' );
		container.classList.add( 'reference-list' );

		const list = document.createElement( 'div' );
		list.classList.add( 'reference-list-items' );

		const expandButton = document.createElement( 'button' );
		expandButton.setAttribute( 'aria-expanded', "false" );
		const label = document.createElement( 'span' );
		label.textContent = mw.message( 'chat-reference-list-button' ).text();
		expandButton.appendChild( label );
		expandButton.classList.add( 'expand-button' );
		expandButton.addEventListener( 'click', () => {
			container.classList.toggle( 'expanded' );
			if ( !container.classList.contains( 'expanded' ) ) {
				expandButton.setAttribute( 'aria-expanded', "false" );
				Dom.scroll( expandButton, "end" );
				references.forEach( ( reference ) => {
					reference.closeAttachments();
				} );
			} else {
				expandButton.setAttribute( 'aria-expanded', "true" );
			}
		} );

		container.appendChild( expandButton );
		container.appendChild( list );

		references.sort( ( a, b ) => a.docRefId - b.docRefId ).forEach( ( reference ) => {
			list.insertAdjacentHTML( 'beforeend', reference.getLinkListRefId() );
			list.insertAdjacentHTML( 'beforeend', reference.getLinkListItem() );
			list.appendChild( reference.getAttachments() );
		} );

		this.messageTextContainer.appendChild( container );
	}

	public appendMessageButtons( allDocuments: HistoryDocument[], resultId?: string, queryId?: string ): void {
		const btns = document.createElement( 'div' );
		btns.classList.add( 'message-buttons' );
		const copyBtn = this.createCopyButton();
		copyBtn.setAttribute( "title", mw.message( 'chat-copy-button-title' ).text() );
		btns.appendChild( copyBtn );

		if ( resultId && queryId ) {
			const feedbackBtn = this.createFeedbackButtons( resultId, queryId );
			btns.appendChild( feedbackBtn );
		}

		const roleLookup = new RoleLookup();
		if ( roleLookup.userHasRole( 'maintainer' ) && allDocuments.length > 0 ) {
			const sourcesBtn = this.createSourcesButton( allDocuments );
			btns.appendChild( sourcesBtn );
		}

		this.messageContainer.appendChild( btns );
	}

	private createCopyButton(): HTMLElement {
		const buttonCopy = document.createElement( 'button' );
		buttonCopy.classList.add( 'copy' );

		buttonCopy.addEventListener( 'click', () => {
			const text = MessageParser.parseMarkup( this.message ).replace( /<[^>]*>/g, '' );
			navigator.clipboard.writeText( text );
			mw.notify( mw.message( 'chat-copy-to-clipboard' ).text() );
		} );

		return buttonCopy;
	}

	private createSourcesButton( allDocuments ): HTMLElement {
		const btn = document.createElement( 'button' );
		btn.classList.add( 'sources' );

		btn.addEventListener( 'click', () => {
			if ( !this.allDocumentsPanel ) {
				this.allDocumentsPanel = new AllDocumentsPanel( this.messageTextContainer, allDocuments );
			}
			this.allDocumentsPanel.toggle();
		} );
		return btn;
	}

	private createFeedbackButtons( resultId: string, queryId: string ): HTMLElement {
		const feedbackButtons = document.createElement( 'div' );
		feedbackButtons.classList.add( 'feedback-buttons' );

		const buttonAccurate = document.createElement( 'button' );
		buttonAccurate.classList.add( 'accurate' );
		buttonAccurate.setAttribute( "title", mw.message( 'chat-accurate-feedback-button-title' ).text() );
		buttonAccurate.dataset.score = FeedbackScore.ACCURATE;

		const buttonFairlyAccurate = document.createElement( 'button' );
		buttonFairlyAccurate.classList.add( 'fairly-accurate' );
		buttonFairlyAccurate.setAttribute( "title", mw.message( 'chat-fairly-accurate-feedback-button-title' ).text() );
		buttonFairlyAccurate.dataset.score = FeedbackScore.FAIRLY_ACCURATE;

		const buttonInaccurate = document.createElement( 'button' );
		buttonInaccurate.classList.add( 'inaccurate' );
		buttonInaccurate.setAttribute( "title", mw.message( 'chat-inaccurate-feedback-button-title' ).text() );
		buttonInaccurate.dataset.score = FeedbackScore.INACCURATE;

		const feedbackEvent: FeedbackEvent = {
			resultId,
			queryId,
			score: FeedbackScore.ACCURATE,
			comment: '',
			tags: []
		}

		let lastFeedbackScore = null;
		const buttons = {};
		buttons[ FeedbackScore.ACCURATE ] = buttonAccurate;
		buttons[ FeedbackScore.FAIRLY_ACCURATE ] = buttonFairlyAccurate;
		buttons[ FeedbackScore.INACCURATE ] = buttonInaccurate;

		const clearActiveButtons = () => {
			for ( const key in buttons ) {
				if ( buttons[ key ].classList.contains( 'active' ) ) {
					buttons[ key ].classList.remove( 'active' );
				}
			}
		}

		const toggleClickedClasses = ( clickedButton: HTMLButtonElement ) => {
			clearActiveButtons();
			clickedButton.classList.add( 'active' );
			this.element.classList.add( 'active-popup' );
			if ( !this.feedbackPanel ) {
				this.feedbackPanel = new FeedbackPanel( this.messageTextContainer );
				this.feedbackPanel.on( 'cancel', () => {
					clearActiveButtons();
					if ( lastFeedbackScore ) {
						buttons[ lastFeedbackScore ].classList.add( 'active' );
					}
					this.element.classList.remove( 'active-popup' );
				} );
				this.feedbackPanel.on( 'feedback-send', ( feedback: Feedback ) => {
					feedback.score = feedbackEvent.score;
					feedback.resultId = resultId;
					feedback.queryId = queryId;
					feedback.answer = this.message;
					this.sendMail( feedback );

					feedbackEvent.tags = feedback.issue;
					feedbackEvent.comment = feedback.comment;
					lastFeedbackScore = feedback.score;
					this.emit( MessageReceived.FEEDBACK_EVENT, feedbackEvent );
				} );
			}
			this.feedbackPanel.show();
		}

		buttonAccurate.addEventListener( 'click', () => {
			feedbackEvent.score = FeedbackScore.ACCURATE;
			clearActiveButtons();
			buttonAccurate.classList.add( 'active' );
			feedbackEvent.tags = [];
			if ( this.feedbackPanel ) {
				this.feedbackPanel.hide();
			}
			lastFeedbackScore = FeedbackScore.ACCURATE;
			this.emit( MessageReceived.FEEDBACK_EVENT, feedbackEvent );
		} );

		buttonFairlyAccurate.addEventListener( 'click', () => {
			feedbackEvent.score = FeedbackScore.FAIRLY_ACCURATE;
			toggleClickedClasses( buttonFairlyAccurate );
		} );

		buttonInaccurate.addEventListener( 'click', () => {
			feedbackEvent.score = FeedbackScore.INACCURATE;
			toggleClickedClasses( buttonInaccurate );
		} );

		feedbackButtons.appendChild( buttonAccurate );
		feedbackButtons.appendChild( buttonFairlyAccurate );
		feedbackButtons.appendChild( buttonInaccurate );

		return feedbackButtons;
	}

	private sendMail( feedback: Feedback ) {
		const storage = new BrowserStorage();
		const feedbackChat = storage.getRawChatHistory();
		const mailData = {
			issue: feedback.issue,
			cause: feedback.cause,
			comment: feedback.comment,
			mail: feedback.mail,
			resultId: feedback.resultId,
			score: feedback.score,
			lastAnswer: null,
			contextAnswers: []
		}
		if ( feedbackChat ) {
			const lastAnswer = feedbackChat.pop();
			const historyItems = this.getMailContextChatItems( feedbackChat );

			mailData.lastAnswer = {
				query: lastAnswer.query.query,
				answer: MessageParser.parseMarkup( lastAnswer.answer ),
				references: lastAnswer.references,
				followUpType: lastAnswer.query.type === 'followUp' ? lastAnswer.query.followUpType : ''
			}
			mailData.contextAnswers = historyItems;
		}

		this.bluespiceApi.sendFeedbackMail( mailData );
	}

	private getMailContextChatItems( chatHistory: ChatHistoryItem[] ): any[] {
		// Get last 2 normal answers (and all follow ups in between) to form the context
		// for the mail (since we already send the last answer this will make it 3 last question/answers)
		const contextAnswers = [];
		let normalAnswers = 0;
		chatHistory = chatHistory.reverse();
		chatHistory.forEach( ( chatItem ) => {
			if ( normalAnswers >= 3 ) {
				return;
			}
			if ( chatItem.query.type === 'normal' ) {
				normalAnswers++;
				contextAnswers.push( {
					query: chatItem.query.query,
					answer: MessageParser.parseMarkup( chatItem.answer ),
					references: chatItem.references,
					followUpType: ''
				} );
			} else if ( chatItem.query.type === 'followUp' ) {
				contextAnswers.push( {
					query: chatItem.query.query,
					answer: MessageParser.parseMarkup( chatItem.answer ),
					references: chatItem.references,
					followUpType: chatItem.query.followUpType
				} );
			}
		} );
		return contextAnswers.reverse();
	}
}
