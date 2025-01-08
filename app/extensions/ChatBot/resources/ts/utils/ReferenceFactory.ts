import Reference from "../model/Reference";
import { ReferenceDocument, ResponseMeta, ResponseReference } from "../api/DeepsetApi";

export interface ReferenceDocumentMeta {
	extension: string;
	prefixed_title: string;
	attachments?: string[];
	sections?: string[];
	file_name?: string;
	basename: string;
	namespace: number;
	namespace_text: string;
	uri: string;
}

export default class ReferenceFactory {

	public static createFromResponseData( meta: ResponseMeta, documents: ReferenceDocument[] ): {
		valid: Reference[],
		missing: number[]
	} {
		const references: Reference[] = [];
		const missing = [];

		( meta._references || [] ).forEach( ( responseRef: ResponseReference ) => {
			let reference = references.find( ( r ) => r.docRefId === responseRef.document_position );
			if ( reference === undefined ) {
				let documentMeta = null;
				try {
					documentMeta = ReferenceFactory.findPageInDocuments( responseRef.document_id, documents );
				} catch ( error ) {
					missing.push( responseRef.document_id );
					return;
				}

				reference = new Reference( responseRef.document_position, documentMeta );
				references.push( reference );
			}
			reference.addDocumentPosition( responseRef.answer_start_idx );
		} );

		return {
			valid: references,
			missing
		};
	}

	public static createFromJson( data: any ): Reference {
		const reference = new Reference( data.docRefId, data.meta );
		reference.documentPositions = data.documentPositions;

		return reference;
	}

	private static findPageInDocuments( documentId: string, documents: ReferenceDocument[] ): ReferenceDocumentMeta {
		const document = documents.find( ( document ) => document.id === documentId );

		if ( !document ) {
			throw new Error( 'Document not found in documents' );
		}

		return document.meta;
	}
}
