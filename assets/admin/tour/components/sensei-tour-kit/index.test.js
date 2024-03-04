/**
 * Internal dependencies
 */
import SenseiTourKit from './index';
import getTourSteps from '../../course-tour/steps';
/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render } from '@testing-library/react';
import { WpcomTourKit } from '@automattic/tour-kit';
import { when } from 'jest-when';
/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

jest.mock( '@automattic/tour-kit', () => ( {
	WpcomTourKit: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockImplementation( () => ( {} ) ),
	createReduxStore: jest.fn(),
	register: jest.fn(),
	useSelect: jest.fn().mockImplementation( () => ( {} ) ),
} ) );

const mockFunction = jest.fn();

describe( 'SenseiTourKit', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		when( WpcomTourKit ).mockImplementation( ( props ) => {
			mockFunction( props );
			return (
				<div>
					<button
						data-testid="closeButton"
						onClick={ () => props.config.closeHandler() }
					>
						Close
					</button>
					<button
						data-testid="nextButton"
						onClick={ () =>
							props.config.options.callbacks.onStepViewOnce( 1 )
						}
					></button>
					<h1>WpcomTourKit output</h1>
				</div>
			);
		} );
	} );

	test( 'should render wpcomtourkit as expected', () => {
		const steps = getTourSteps();
		useSelect.mockImplementation( () => ( {
			showTour: true,
		} ) );

		const { queryByText } = render( <SenseiTourKit steps={ steps } /> );
		expect( queryByText( 'WpcomTourKit output' ) ).toBeTruthy();
	} );

	test( 'should pass the correct steps to wpcomtourkit', () => {
		const steps = getTourSteps();

		render( <SenseiTourKit steps={ steps } /> );

		expect(
			mockFunction.mock.calls[ 0 ][ 0 ].config.steps[ 5 ].slug
		).toEqual( steps[ 5 ].slug );
	} );

	test( 'should pass the merged config to wpcomtourkit', () => {
		const steps = getTourSteps();

		const dummyConfig = {
			options: {
				effects: {
					liveResize: {
						rootElementSelector: '.toot',
					},
				},
			},
		};

		render( <SenseiTourKit steps={ steps } extraConfig={ dummyConfig } /> );

		expect(
			mockFunction.mock.calls[ 0 ][ 0 ].config.steps[ 5 ].slug
		).toEqual( steps[ 5 ].slug );
		expect(
			mockFunction.mock.calls[ 0 ][ 0 ].config.options.effects.liveResize
				.rootElementSelector
		).toEqual( '.toot' );
	} );

	test( 'should close the tour when closeHandler is called', () => {
		const setTourShowStatus = jest.fn();

		useDispatch.mockReturnValue( { setTourShowStatus } );

		const { getByTestId } = render(
			<SenseiTourKit tourName="test-tour" steps={ [] } />
		);

		fireEvent.click( getByTestId( 'closeButton' ) );

		expect( setTourShowStatus ).toHaveBeenCalledWith(
			false,
			true,
			'test-tour'
		);
	} );

	test( 'should call the event log function when step is viewed', () => {
		useSelect.mockImplementation( () => ( {
			showTour: true,
		} ) );
		window.sensei_log_event = jest.fn();

		const { getByTestId } = render(
			<SenseiTourKit
				trackId="test-tracks-id"
				tourName="test-tour"
				steps={ [
					{
						slug: 'step-1',
					},
					{
						slug: 'step-2',
					},
					{
						slug: 'step-2',
					},
				] }
			/>
		);

		fireEvent.click( getByTestId( 'nextButton' ) );

		expect(
			window.sensei_log_event
		).toHaveBeenCalledWith( 'test-tracks-id', { step: 'step-2' } );
	} );
} );
