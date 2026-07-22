import WalletItem from "./WalletItem";

function WalletList({ wallets, prices, onDeleted, onBalanceLoaded, onRenamed }) {
  return (
    <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
      {wallets.map(wallet => (
        <li key={wallet.id}>
          <WalletItem
            wallet={wallet}
            prices={prices}
            onDeleted={onDeleted}
            onBalanceLoaded={onBalanceLoaded}
            onRenamed={onRenamed}
          />
        </li>
      ))}
    </ul>
  );
}

export default WalletList;
