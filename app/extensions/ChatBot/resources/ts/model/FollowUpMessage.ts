import Message from "./Message";

export default class FollowUpMessage extends Message {

	public constructor( message: string | null, timestamp: string ) {
		super( message, timestamp );

		const followUpLabel = document.createElement( 'span' );
		followUpLabel.classList.add( 'follow-up-label' );
		followUpLabel.textContent = mw.msg( 'chatbot-follow-up' );
		this.element.prepend( followUpLabel );
		this.element.setAttribute( 'aria-hidden', 'true' );
	}
}
