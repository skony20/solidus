import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ClientList from '../ClientList.vue'
import type { Client } from '../../../stores/clients'

/**
 * Testy tabeli klientow.
 *
 * ClientList nie zna store'a ani API - dostaje gotowa liste w propsach.
 * Dzieki temu test nie potrzebuje backendu ani bazy i sprawdza dokladnie
 * to, co widzi uzytkownik.
 */

function makeClient(overrides: Partial<Client> = {}): Client {
  return {
    id: 1,
    name: 'Piekarnia Złoty Kłos sp. z o.o.',
    nip: '5270000001',
    email: 'biuro@zlotyklos.pl',
    phone: null,
    address: null,
    status: 'active',
    statusLabel: 'Aktywny',
    notes: null,
    createdAt: '2026-09-01T10:00:00+00:00',
    updatedAt: '2026-09-01T10:00:00+00:00',
    ...overrides,
  }
}

describe('ClientList', () => {
  it('pokazuje komunikat o wczytywaniu zamiast pustej tabeli', () => {
    const wrapper = mount(ClientList, { props: { clients: [], isLoading: true } })

    expect(wrapper.text()).toContain('Wczytywanie...')
    expect(wrapper.findAll('[data-test="client-row"]')).toHaveLength(0)
  })

  it('podpowiada, co zrobic, gdy biuro nie ma jeszcze zadnego klienta', () => {
    const wrapper = mount(ClientList, { props: { clients: [], isLoading: false } })

    expect(wrapper.text()).toContain('Brak klientów')
  })

  it('renderuje wiersz dla kazdego klienta', () => {
    const clients = [makeClient(), makeClient({ id: 2, name: 'Nova Corp sp. z o.o.', nip: null })]

    const wrapper = mount(ClientList, { props: { clients, isLoading: false } })

    expect(wrapper.findAll('[data-test="client-row"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('Piekarnia Złoty Kłos sp. z o.o.')
    expect(wrapper.text()).toContain('Nova Corp sp. z o.o.')
  })

  it('formatuje NIP w grupy, a przy jego braku pokazuje myslnik', () => {
    const clients = [makeClient(), makeClient({ id: 2, nip: null })]

    const wrapper = mount(ClientList, { props: { clients, isLoading: false } })

    expect(wrapper.text()).toContain('527-00-00-001')
    expect(wrapper.text()).toContain('—')
  })

  it('pokazuje etykiete statusu wielkimi literami', () => {
    const wrapper = mount(ClientList, {
      props: { clients: [makeClient({ statusLabel: 'Wdrozenie', status: 'onboarding' })], isLoading: false },
    })

    expect(wrapper.text()).toContain('WDROZENIE')
  })

  it('emituje "select" po klknieciu w wiersz', async () => {
    const client = makeClient()
    const wrapper = mount(ClientList, { props: { clients: [client], isLoading: false } })

    await wrapper.find('[data-test="client-row"]').trigger('click')

    expect(wrapper.emitted('select')?.[0]).toEqual([client])
  })

  it('emituje "remove" po kliknieciu kosza i nie otwiera przy tym klienta', async () => {
    const client = makeClient()
    const wrapper = mount(ClientList, { props: { clients: [client], isLoading: false } })

    await wrapper.find('[data-test="client-remove"]').trigger('click')

    expect(wrapper.emitted('remove')?.[0]).toEqual([client])
    // Kliknięcie w kosz ma zatrzymać propagację - inaczej użytkownik
    // zostałby przeniesiony na ekran klienta, którego właśnie usuwa.
    expect(wrapper.emitted('select')).toBeUndefined()
  })
})
