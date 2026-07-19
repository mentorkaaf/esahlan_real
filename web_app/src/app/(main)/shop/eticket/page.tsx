'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ModuleHeader, Spinner, Empty } from '../efood/page';

interface City { id: number; name: string; code?: string; }
interface Flight {
  id: number; flight_number?: string; airline?: string;
  departure_city: string; arrival_city: string;
  departure_time: string; arrival_time: string;
  duration?: string; price: number; class?: string;
  available_seats?: number;
}

type Class = 'economy' | 'business' | 'first';

export default function ETicketPage() {
  const [cities, setCities] = useState<City[]>([]);
  const [loading, setLoading] = useState(true);
  const [searched, setSearched] = useState(false);
  const [flights, setFlights] = useState<Flight[]>([]);
  const [searching, setSearching] = useState(false);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [date, setDate] = useState('');
  const [passengers, setPassengers] = useState(1);
  const [flightClass, setFlightClass] = useState<Class>('economy');
  const [selectedFlight, setSelectedFlight] = useState<Flight | null>(null);
  const [booking, setBooking] = useState(false);
  const [success, setSuccess] = useState(false);
  const [passengerName, setPassengerName] = useState('');
  const [passengerPhone, setPassengerPhone] = useState('');
  const router = useRouter();

  useEffect(() => {
    api.get<{ data: City[] }>('/eticket/cities').then(r => setCities(Array.isArray(r.data) ? r.data : [])).catch(() => {}).finally(() => setLoading(false));
  }, []);

  async function searchFlights() {
    if (!from || !to || !date) return;
    setSearching(true);
    setSearched(true);
    setFlights([]);
    setSelectedFlight(null);
    try {
      const res = await api.post<{ data: Flight[] }>('/eticket/search', {
        from_city_id: from, to_city_id: to, date, passengers, class: flightClass,
      });
      setFlights(Array.isArray(res.data) ? res.data : []);
    } catch { setFlights([]); }
    setSearching(false);
  }

  async function bookFlight() {
    if (!selectedFlight || !passengerName || !passengerPhone) return;
    setBooking(true);
    try {
      await api.post('/eticket/book', {
        flight_id: selectedFlight.id, passengers,
        class: flightClass, passenger_name: passengerName,
        passenger_phone: passengerPhone,
      });
      setSuccess(true);
    } catch { }
    setBooking(false);
  }

  function fmtTime(dt: string) {
    try { return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); } catch { return dt; }
  }

  if (success) return (
    <div className="max-w-2xl mx-auto">
      <ModuleHeader title="eTicket" emoji="✈️" onBack={() => router.push('/shop')} />
      <div className="flex flex-col items-center py-20 px-8 text-center">
        <div className="text-5xl mb-4">🎫</div>
        <h2 className="font-black text-xl text-gray-900 dark:text-white mb-2">Booking Confirmed!</h2>
        <p className="text-gray-400 text-sm mb-1">{selectedFlight?.airline ?? 'Flight'} · {passengers} passenger(s)</p>
        <p className="text-gray-400 text-sm mb-6">{cities.find(c => String(c.id) === from)?.name} → {cities.find(c => String(c.id) === to)?.name}</p>
        <button onClick={() => { setSuccess(false); setSelectedFlight(null); setSearched(false); setFlights([]); }} className="px-6 py-3 bg-[#FF8A00] text-white font-black rounded-2xl">New Search</button>
      </div>
    </div>
  );

  return (
    <div className="max-w-2xl mx-auto pb-10">
      <ModuleHeader title="eTicket" emoji="✈️" onBack={() => router.push('/shop')} />
      {loading ? <Spinner /> : (
        <div className="px-4 space-y-4">
          {/* Search form */}
          <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-4 space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">From</label>
                <select value={from} onChange={e => setFrom(e.target.value)} className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30">
                  <option value="">Select city</option>
                  {cities.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
              <div>
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">To</label>
                <select value={to} onChange={e => setTo(e.target.value)} className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30">
                  <option value="">Select city</option>
                  {cities.filter(c => String(c.id) !== from).map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
            </div>
            <div className="grid grid-cols-3 gap-3">
              <div className="col-span-2">
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</label>
                <input type="date" value={date} onChange={e => setDate(e.target.value)} min={new Date().toISOString().split('T')[0]}
                  className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
              </div>
              <div>
                <label className="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Passengers</label>
                <select value={passengers} onChange={e => setPassengers(Number(e.target.value))} className="mt-1 w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none">
                  {[1,2,3,4,5,6].map(n => <option key={n} value={n}>{n}</option>)}
                </select>
              </div>
            </div>
            <div className="flex gap-2">
              {(['economy', 'business', 'first'] as Class[]).map(c => (
                <button key={c} onClick={() => setFlightClass(c)}
                  className={`flex-1 py-2 rounded-xl text-xs font-black capitalize transition-colors ${flightClass === c ? 'bg-[#FF8A00] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'}`}>
                  {c}
                </button>
              ))}
            </div>
            <button onClick={searchFlights} disabled={!from || !to || !date || searching}
              className="w-full py-3 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40">
              {searching ? 'Searching…' : '🔍 Search Flights'}
            </button>
          </div>

          {/* Results */}
          {searched && !searching && (
            flights.length === 0
              ? <Empty text="No flights found for this route and date." />
              : <div className="space-y-3">
                  <h2 className="font-black text-gray-900 dark:text-white">{flights.length} Flight{flights.length !== 1 ? 's' : ''} Found</h2>
                  {flights.map(f => (
                    <button key={f.id} onClick={() => setSelectedFlight(prev => prev?.id === f.id ? null : f)}
                      className={`w-full bg-white dark:bg-gray-900 rounded-2xl border-2 p-4 text-left transition-colors ${selectedFlight?.id === f.id ? 'border-[#FF8A00]' : 'border-gray-100 dark:border-gray-800'}`}>
                      <div className="flex items-center justify-between mb-2">
                        <p className="text-xs font-bold text-gray-400">{f.airline ?? 'Airline'} {f.flight_number ? `· ${f.flight_number}` : ''}</p>
                        <p className="font-black text-[#FF8A00]">${f.price}</p>
                      </div>
                      <div className="flex items-center gap-3">
                        <div className="text-center">
                          <p className="font-black text-lg text-gray-900 dark:text-white">{fmtTime(f.departure_time)}</p>
                          <p className="text-xs text-gray-400">{cities.find(c => String(c.id) === from)?.name ?? f.departure_city}</p>
                        </div>
                        <div className="flex-1 flex flex-col items-center">
                          <p className="text-[10px] text-gray-400">{f.duration ?? '—'}</p>
                          <div className="w-full flex items-center gap-1 mt-0.5">
                            <div className="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600" />
                            <div className="flex-1 h-px bg-gray-200 dark:bg-gray-700" />
                            <span className="text-xs">✈️</span>
                            <div className="flex-1 h-px bg-gray-200 dark:bg-gray-700" />
                            <div className="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600" />
                          </div>
                        </div>
                        <div className="text-center">
                          <p className="font-black text-lg text-gray-900 dark:text-white">{fmtTime(f.arrival_time)}</p>
                          <p className="text-xs text-gray-400">{cities.find(c => String(c.id) === to)?.name ?? f.arrival_city}</p>
                        </div>
                      </div>
                      {f.available_seats !== undefined && <p className="text-[10px] text-gray-400 mt-2">{f.available_seats} seats left</p>}
                    </button>
                  ))}

                  {selectedFlight && (
                    <div className="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 p-4 space-y-3">
                      <h3 className="font-black text-gray-900 dark:text-white">Passenger Details</h3>
                      <input value={passengerName} onChange={e => setPassengerName(e.target.value)} placeholder="Full name"
                        className="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
                      <input value={passengerPhone} onChange={e => setPassengerPhone(e.target.value)} placeholder="Phone" type="tel"
                        className="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#FF8A00]/30" />
                      <button onClick={bookFlight} disabled={!passengerName || !passengerPhone || booking}
                        className="w-full py-3.5 bg-[#FF8A00] text-white font-black rounded-2xl disabled:opacity-40">
                        {booking ? 'Booking…' : `Book · $${selectedFlight.price * passengers}`}
                      </button>
                    </div>
                  )}
                </div>
          )}
        </div>
      )}
    </div>
  );
}
