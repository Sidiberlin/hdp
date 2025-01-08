export default class RoleLookup {

	private roles = {};

	public userHasRole( role: string ): boolean {
		this.assertRoles();
		return this.roles.hasOwnProperty( role ) && this.roles[role];
	}

	private assertRoles() {
		if ( !this.roles || Object.keys( this.roles ).length === 0 ) {
			this.roles = mw.config.get( 'bmbfRoles' );
		}
	}
}
