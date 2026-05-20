/**
 * Generic settings card wrapper.
 *
 * @param {{icon: JSX.Element, title: string, children: JSX.Element}} props
 * @return {JSX.Element}
 */
export default function SettingsCard( { icon, title, children } ) {
	return (
		<div className="lswl-settings-card">
			<div className="lswl-settings-card__header">
				<span className="lswl-settings-card__icon">{ icon }</span>
				<h3 className="lswl-settings-card__title">{ title }</h3>
			</div>
			<div className="lswl-settings-card__body">{ children }</div>
		</div>
	);
}
