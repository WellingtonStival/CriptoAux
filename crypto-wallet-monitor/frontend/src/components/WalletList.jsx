import WalletItem from "./WalletItem";

function WalletList({ wallets, prices, onDeleted, onBalanceLoaded }) {
  return (
    <ul className="flex flex-col gap-3">
      {wallets.map(wallet => (
        <WalletItem
          key={wallet.id}
          wallet={wallet}
          prices={prices}
          onDeleted={onDeleted}
          onBalanceLoaded={onBalanceLoaded}
        />
      ))}
    </ul>
  );
}

export default WalletList;
