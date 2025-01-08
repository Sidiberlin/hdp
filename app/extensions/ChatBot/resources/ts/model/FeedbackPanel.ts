import Panel from "./Panel";
import Dom from "../utils/Dom";

export interface Feedback {
	issue: string[];
	cause: string;
	comment: string;
	mail: string;
	score?: string;
	tags?: string[];
	resultId?: string;
	queryId?: string;
	answer?: string;
}

export default class FeedbackPanel extends Panel {

	protected dialog: HTMLElement;

	protected closeButton: HTMLButtonElement;

	protected selectedIssues: string[] = [];

	protected selectedCause: string;

	private desc: HTMLTextAreaElement;

	private mail: HTMLInputElement;

	private sendButton: HTMLButtonElement;

	public constructor( appendTo: HTMLElement ) {
		super( mw.msg( 'chat-popup-feedback-problem-heading' ), appendTo );
		this.element.classList.add( 'panel-feedback' );
	}

	protected buildContent(): HTMLElement {
		const contentCnt = document.createElement( 'div' );
		contentCnt.classList.add( 'popup-body-cnt' );

		const problemCnt = this.getProblemSection();
		contentCnt.appendChild( problemCnt );
		const causeCnt = this.getCauseSection();
		contentCnt.appendChild( causeCnt );

		const descCnt = this.getDescriptionSection();
		contentCnt.appendChild( descCnt );

		const mailCnt = this.getMailSection();
		contentCnt.appendChild( mailCnt );

		const sendBtnCnt = document.createElement( 'div' );
		sendBtnCnt.classList.add( 'popup-feedback-send' );
		this.sendButton = document.createElement( 'button' );
		this.sendButton.textContent = mw.message( 'chat-popup-feedback-send-button-label' ).text();
		this.sendButton.disabled = true;
		this.sendButton.addEventListener( 'click', this.sendFeedback.bind( this ) );
		sendBtnCnt.appendChild( this.sendButton );
		contentCnt.appendChild( sendBtnCnt );

		return contentCnt;
	}

	private getMailSection(): HTMLElement {
		const mailCnt = document.createElement( 'div' );
		mailCnt.classList.add( 'popup-feedback-mail' );

		const mailHeading = document.createElement( 'p' );
		mailHeading.classList.add( 'feedback-section-title' );
		mailHeading.textContent = mw.message( 'chat-popup-feedback-problem-mail' ).text();
		mailHeading.textContent += ' ' + mw.message( 'chat-popup-feedback-problem-optional' ).text();
		mailCnt.appendChild( mailHeading );

		this.mail = document.createElement( 'input' );
		this.mail.type = 'email';
		mailCnt.appendChild( this.mail );
		return mailCnt;
	}

	private getDescriptionSection(): HTMLElement {
		const descCnt = document.createElement( 'div' );
		descCnt.classList.add( 'popup-feedback-description' );

		const descHeading = document.createElement( 'p' );
		descHeading.classList.add( 'feedback-section-title' );
		descHeading.textContent = mw.message( 'chat-popup-feedback-problem-desc' ).text();
		descHeading.textContent += ' ' + mw.message( 'chat-popup-feedback-problem-optional' ).text();
		descCnt.appendChild( descHeading );

		this.desc = document.createElement( 'textarea' );
		descCnt.appendChild( this.desc );
		return descCnt;
	}

	private getCauseSection(): HTMLElement {
		const causeCnt = document.createElement( 'div' );
		causeCnt.classList.add( 'popup-feedback-cause' );

		const causeHeading = document.createElement( 'p' );
		causeHeading.classList.add( 'feedback-section-title' );
		causeHeading.textContent = mw.message( 'chat-popup-feedback-problem-cause' ).text();
		causeCnt.appendChild( causeHeading );

		const causes = document.createElement( 'div' );
		const causeSelections = mw.config.get( 'bmbfFeedbackAnswerCauseSelection' ) as string[];
		causeSelections.forEach( ( issue ) => {
			const causeEl = document.createElement( 'button' );
			causeEl.classList.add( 'feedback-option' );
			causeEl.textContent = issue;
			causeEl.addEventListener( 'click', this.handleCauseList.bind( this ) );
			causes.appendChild( causeEl );
		} );
		const selection = 0;
		causes.children[ selection ].classList.add( 'selected' );
		this.selectedCause = causes.children[ selection ].textContent;
		causeCnt.appendChild( causes );

		return causeCnt;
	}

	private getProblemSection(): HTMLElement {
		const problemCnt = document.createElement( 'div' );
		problemCnt.classList.add( 'popup-feedback-problem-selection' );

		const problems = document.createElement( 'div' );
		const problemSelections = mw.config.get( 'bmbfFeedbackAnswerProblemSelection' ) as string[];
		problemSelections.forEach( ( issue ) => {
			const problemEl = document.createElement( 'button' );
			problemEl.classList.add( 'feedback-option' )
			problemEl.textContent = issue;
			problemEl.addEventListener( 'click', this.handleIssueList.bind( this ) );
			problems.appendChild( problemEl );
		} );
		problemCnt.appendChild( problems );

		return problemCnt;
	}

	private handleIssueList( event ): void {
		const target = event.target as HTMLAnchorElement;
		const issue = target.textContent;
		if ( target.classList.contains( 'selected' ) ) {
			target.classList.remove( 'selected' );
			const index = this.selectedIssues.indexOf( issue, 0 );
			if ( index > -1 ) {
				this.selectedIssues.splice( index, 1 );
			}
			if ( this.selectedIssues.length > 1 ) {
				this.sendButton.disabled = false;
			} else {
				this.sendButton.disabled = true;
			}
		} else {
			target.classList.add( 'selected' );
			this.selectedIssues.push( issue );
			this.sendButton.disabled = false;
		}
	}

	public show(): void {
		super.show();
		Dom.scroll( this.sendButton );
	}

	private handleCauseList( event ): void {
		const target = event.target as HTMLAnchorElement;
		const issue = target.textContent;
		if ( !target.classList.contains( 'selected' ) ) {
			const currentlySelected = document.querySelector( '.popup-feedback-cause .selected' );
			currentlySelected.classList.remove( 'selected' );
			target.classList.add( 'selected' );
			this.selectedCause = issue;
		}
	}

	private sendFeedback(): void {
		const feedback = {
			'issue': this.selectedIssues,
			'cause': this.selectedCause,
			'comment': this.desc.value,
			'mail': this.mail.value
		} as Feedback;
		this.emit( 'feedback-send', feedback );
		this.hide();
	}
}
