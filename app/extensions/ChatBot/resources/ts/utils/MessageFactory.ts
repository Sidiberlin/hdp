import Message from "../model/Message";
import MessageReceived from "../model/MessageReceived";
import Reference from "../model/Reference";
import Dom from "./Dom";
import FollowUpMessage from "../model/FollowUpMessage";

declare const mw: any;

export default class MessageFactory {
	private readonly dom: Dom;

	private static readonly GREETING_ATTR = 'greeting';

	public constructor( dom: Dom ) {
		this.dom = dom;
	}

	public createSentMessage( messageText: string, isoTimestamp: string = new Date().toISOString() ): Message {
		const message = new Message( messageText, isoTimestamp );
		message.addClass( 'sent' );

		this.dom.appendMessage( message );

		return message;
	}

	public createReceivedMessage(
		query: string,
		messageText: string = null,
		references: Reference[] = [],
		isoTimestamp: string = new Date().toISOString()
	): MessageReceived {
		const message = new MessageReceived( query, messageText, isoTimestamp );
		message.addClass( 'received' );

		this.dom.appendMessage( message );

		return message;
	}

	public createGreetingMessage(): void {
		const message = new Message( mw.message( 'chat-greeting' ).text(), new Date().toISOString() );
		message.addClass( 'received' );
		message.addAttribute( MessageFactory.GREETING_ATTR );

		this.dom.appendMessage( message );
	}

	public createFollowUpMessage( messageText: string, isoTimestamp: string = new Date().toISOString() ): FollowUpMessage {
		const message = new FollowUpMessage( messageText, isoTimestamp );
		message.addClass( 'sent' );

		this.dom.appendMessage( message );

		return message;
	}
}
